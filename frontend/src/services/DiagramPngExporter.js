const HEADER_HEIGHT = 40
const ROW_HEIGHT = 40
const PADDING = 80
const MAX_OUTPUT_DIMENSION = 16000
const MAX_OUTPUT_PIXELS = 32_000_000

const parseWidth = (node) => {
    const width = Number.parseFloat(node.style?.width)
    return Number.isFinite(width) ? width : 400
}

const finiteNumber = (value, fallback = 0) => {
    const number = Number(value)
    return Number.isFinite(number) ? number : fallback
}

const safeFilename = (filename) => (filename || 'schema').replace(/[<>:"/\\|?*\u0000-\u001f]/g, '_')

const roundedRect = (ctx, x, y, width, height, radius) => {
    const r = Math.min(radius, width / 2, height / 2)
    ctx.beginPath()
    ctx.moveTo(x + r, y)
    ctx.lineTo(x + width - r, y)
    ctx.quadraticCurveTo(x + width, y, x + width, y + r)
    ctx.lineTo(x + width, y + height - r)
    ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height)
    ctx.lineTo(x + r, y + height)
    ctx.quadraticCurveTo(x, y + height, x, y + height - r)
    ctx.lineTo(x, y + r)
    ctx.quadraticCurveTo(x, y, x + r, y)
    ctx.closePath()
}

const buildExportGraph = (schema) => {
    const tables = []
    const tableById = new Map()
    const rowsByTable = new Map()

    for (const element of schema) {
        if (element.type === 'table') {
            const table = {
                ...element,
                x: finiteNumber(element.position?.x),
                y: finiteNumber(element.position?.y),
                width: parseWidth(element),
                rows: [],
            }
            tables.push(table)
            tableById.set(table.id, table)
        } else if (element.type === 'row' && element.parentNode) {
            if (!rowsByTable.has(element.parentNode)) rowsByTable.set(element.parentNode, [])
            rowsByTable.get(element.parentNode).push(element)
        }
    }

    const rowAnchors = new Map()
    for (const table of tables) {
        table.rows = (rowsByTable.get(table.id) ?? [])
            .sort((a, b) => (a.position?.y ?? 0) - (b.position?.y ?? 0))
        const lastRowBottom = table.rows.reduce(
            (bottom, row) => Math.max(bottom, (row.position?.y ?? HEADER_HEIGHT) + ROW_HEIGHT),
            HEADER_HEIGHT
        )
        table.height = Math.max(HEADER_HEIGHT, lastRowBottom)

        for (const row of table.rows) {
            rowAnchors.set(row.id, {
                table,
                y: table.y + (row.position?.y ?? HEADER_HEIGHT) + (ROW_HEIGHT / 2),
            })
        }
    }

    const edges = schema.filter(element => element.source && element.target)
    return { tables, rowAnchors, edges }
}

const graphBounds = (tables) => {
    const minX = Math.min(...tables.map(table => table.x)) - PADDING
    const minY = Math.min(...tables.map(table => table.y)) - PADDING
    const maxX = Math.max(...tables.map(table => table.x + table.width)) + PADDING
    const maxY = Math.max(...tables.map(table => table.y + table.height)) + PADDING
    return { x: minX, y: minY, width: maxX - minX, height: maxY - minY }
}

const outputScale = ({ width, height }) => Math.min(
    1,
    MAX_OUTPUT_DIMENSION / Math.max(width, height),
    Math.sqrt(MAX_OUTPUT_PIXELS / (width * height))
)

const edgePath = (edge, rowAnchors) => {
    const source = rowAnchors.get(edge.source)
    const target = rowAnchors.get(edge.target)
    if (!source || !target) return null

    const sourceOnLeft = edge.sourceHandle?.includes('left')
    const targetOnRight = edge.targetHandle?.includes('right')
    const sourceX = sourceOnLeft ? source.table.x : source.table.x + source.table.width
    const targetX = targetOnRight ? target.table.x + target.table.width : target.table.x

    return {
        sourceX,
        sourceY: source.y,
        centerX: (sourceX + targetX) / 2,
        targetX,
        targetY: target.y,
    }
}

const drawEdges = (ctx, edges, rowAnchors) => {
    ctx.lineWidth = 2
    ctx.globalAlpha = 0.8

    for (const edge of edges) {
        const path = edgePath(edge, rowAnchors)
        if (!path) continue

        ctx.strokeStyle = edge.data?.color || edge.style?.stroke || '#5d9d7c'
        ctx.beginPath()
        ctx.moveTo(path.sourceX, path.sourceY)
        ctx.lineTo(path.centerX, path.sourceY)
        ctx.lineTo(path.centerX, path.targetY)
        ctx.lineTo(path.targetX, path.targetY)
        ctx.stroke()
    }

    ctx.globalAlpha = 1
}

const drawTable = (ctx, table) => {
    const headerColor = table.data?.color || table.style?.background || '#3d7a5c'

    roundedRect(ctx, table.x, table.y, table.width, table.height, 7)
    ctx.fillStyle = '#323232'
    ctx.fill()
    ctx.strokeStyle = '#484848'
    ctx.lineWidth = 1
    ctx.stroke()

    roundedRect(ctx, table.x, table.y, table.width, HEADER_HEIGHT, 7)
    ctx.fillStyle = headerColor
    ctx.fill()
    ctx.fillRect(table.x, table.y + (HEADER_HEIGHT / 2), table.width, HEADER_HEIGHT / 2)

    ctx.fillStyle = '#ffffff'
    ctx.font = '600 16px Inter, Arial, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(table.label || '', table.x + (table.width / 2), table.y + (HEADER_HEIGHT / 2), table.width - 24)

    ctx.font = '14px Inter, Arial, sans-serif'
    ctx.textAlign = 'left'
    for (const row of table.rows) {
        const rowY = table.y + (row.position?.y ?? HEADER_HEIGHT)
        ctx.strokeStyle = '#484848'
        ctx.beginPath()
        ctx.moveTo(table.x, rowY)
        ctx.lineTo(table.x + table.width, rowY)
        ctx.stroke()

        ctx.fillStyle = '#e0e0e0'
        ctx.fillText(row.label || '', table.x + 12, rowY + (ROW_HEIGHT / 2), table.width - 170)

        ctx.fillStyle = '#aaaaaa'
        ctx.textAlign = 'right'
        ctx.fillText(row.data?.sqlType || '', table.x + table.width - 12, rowY + (ROW_HEIGHT / 2), 145)
        ctx.textAlign = 'left'
    }
}

const canvasBlob = (canvas) => new Promise((resolve, reject) => {
    canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('PNG encoding failed')), 'image/png')
})

const escapeXml = (value) => String(value ?? '').replace(
    /[&<>"']/g,
    character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&apos;',
    })[character]
)

const svgTable = (table) => {
    const headerColor = escapeXml(table.data?.color || table.style?.background || '#3d7a5c')
    const rows = table.rows.map((row) => {
        const rowY = table.y + finiteNumber(row.position?.y, HEADER_HEIGHT)
        const centerY = rowY + (ROW_HEIGHT / 2)

        return `
            <line x1="${table.x}" y1="${rowY}" x2="${table.x + table.width}" y2="${rowY}" stroke="#484848" />
            <text x="${table.x + 12}" y="${centerY}" class="row-name">${escapeXml(row.label)}</text>
            <text x="${table.x + table.width - 12}" y="${centerY}" class="row-type">${escapeXml(row.data?.sqlType)}</text>`
    }).join('')

    return `
        <g aria-label="${escapeXml(table.label)}">
            <rect x="${table.x}" y="${table.y}" width="${table.width}" height="${table.height}" rx="7" fill="#323232" stroke="#484848" />
            <rect x="${table.x}" y="${table.y}" width="${table.width}" height="${HEADER_HEIGHT}" rx="7" fill="${headerColor}" />
            <rect x="${table.x}" y="${table.y + (HEADER_HEIGHT / 2)}" width="${table.width}" height="${HEADER_HEIGHT / 2}" fill="${headerColor}" />
            <text x="${table.x + (table.width / 2)}" y="${table.y + (HEADER_HEIGHT / 2)}" class="table-name">${escapeXml(table.label)}</text>
            ${rows}
        </g>`
}

const buildSvg = ({ tables, rowAnchors, edges }, bounds) => {
    const edgeMarkup = edges.map((edge) => {
        const path = edgePath(edge, rowAnchors)
        if (!path) return ''

        const color = escapeXml(edge.data?.color || edge.style?.stroke || '#5d9d7c')
        return `<path d="M ${path.sourceX} ${path.sourceY} H ${path.centerX} V ${path.targetY} H ${path.targetX}" fill="none" stroke="${color}" stroke-width="2" stroke-linejoin="round" opacity="0.8" vector-effect="non-scaling-stroke" />`
    }).join('')

    return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${Math.ceil(bounds.width)}" height="${Math.ceil(bounds.height)}" viewBox="${bounds.x} ${bounds.y} ${bounds.width} ${bounds.height}" role="img" aria-label="Database diagram">
    <style>
        text { font-family: Inter, Arial, sans-serif; dominant-baseline: middle; }
        .table-name { fill: #fff; font-size: 16px; font-weight: 600; text-anchor: middle; }
        .row-name { fill: #e0e0e0; font-size: 14px; }
        .row-type { fill: #aaa; font-size: 14px; text-anchor: end; }
    </style>
    <rect x="${bounds.x}" y="${bounds.y}" width="${bounds.width}" height="${bounds.height}" fill="#282828" />
    <g>${edgeMarkup}</g>
    <g>${tables.map(svgTable).join('')}</g>
</svg>`
}

export async function exportDiagramPng(schema, filename) {
    const { tables, rowAnchors, edges } = buildExportGraph(schema ?? [])
    if (!tables.length) throw new Error('Cannot export an empty diagram')

    const bounds = graphBounds(tables)
    const scale = outputScale(bounds)
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.ceil(bounds.width * scale))
    canvas.height = Math.max(1, Math.ceil(bounds.height * scale))

    const ctx = canvas.getContext('2d')
    if (!ctx) throw new Error('Canvas rendering is not supported')

    ctx.fillStyle = '#282828'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.scale(scale, scale)
    ctx.translate(-bounds.x, -bounds.y)

    drawEdges(ctx, edges, rowAnchors)
    for (const table of tables) drawTable(ctx, table)

    const blob = await canvasBlob(canvas)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${safeFilename(filename)}.png`
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 0)

    return {
        width: canvas.width,
        height: canvas.height,
        scale,
        tables: tables.length,
        edges: edges.length,
    }
}

export async function exportDiagramSvg(schema, filename) {
    const graph = buildExportGraph(schema ?? [])
    if (!graph.tables.length) throw new Error('Cannot export an empty diagram')

    const bounds = graphBounds(graph.tables)
    const svg = buildSvg(graph, bounds)
    const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${safeFilename(filename)}.svg`
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 0)

    return {
        width: Math.ceil(bounds.width),
        height: Math.ceil(bounds.height),
        tables: graph.tables.length,
        edges: graph.edges.length,
        bytes: blob.size,
    }
}

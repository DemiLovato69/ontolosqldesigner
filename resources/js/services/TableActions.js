import {Position} from "@vue-flow/core";
import {useToast} from 'vue-toast-notification';
const $toast = useToast();

export const TableActions = {

    addTable(diagramRef, TableStyle, name){
    const diagram = diagramRef.value;
    const tableId = Math.random().toString();
        diagramRef.value = [
        ...diagram,
        {
            id: tableId,
            type: 'table',
            label: name,
            data: {
                toolbarPosition: Position.Top,
                toolbarVisible: true
            },
            position: {x: 0, y: 0},
            style: TableStyle,
        }
    ];
    return tableId;
    },

  addRow(diagramRef, nodeProps, rowProps) {

    const diagram = diagramRef.value;
    const RowStyle = {
        display: 'flex',
        border: '1px solid #10b981',
        borderColor: '#898989',
        background: '#ffffff',
        color: '#000000',
        borderRadius: '5px',
        width: '350px',
        height: '40px',
        alignItems: 'center',
        justifyContent: 'space-between',
    }
    const existingRows = diagram.filter(el => el.parentNode === nodeProps.id);
    const position = nodeProps.data.position || {x: 0, y: 0};

    const id =  Math. floor(Math. random() * 100000).toString();

      diagramRef.value = [
        ...diagram,
        {
            id: id,
            type: 'row',
            label: rowProps.rowName,
            position: {x: position.x, y: position.y + 40 + 40 * existingRows.length},
            style: RowStyle,
            draggable: false,
            parentNode: nodeProps.id,
            data: {
                editing: false,
                showModal: false,
                showOptionsModal: false,
                keyMod: rowProps.keyMod,
                sqlType: rowProps.sqlType,
                nullable: rowProps.nullable,
                unsigned: rowProps.unsigned,
            },
        },
    ];
    return id;
    },

  updateConnectionLineType(diagramRef, selectedEdgeRef, relationshipType) {
    const diagram = diagramRef.value;
    const selectedEdge = selectedEdgeRef.value;

    const edgeIndex = diagram.findIndex(el => el.id === selectedEdge.id);
    if (edgeIndex !== -1) {
        diagram[edgeIndex].data.relationshipType = relationshipType;
        diagram[edgeIndex].type = 'chickenFoot';
        if (relationshipType === 'one-to-one') {
            diagram[edgeIndex].data.markerStart = 'none';
            diagram[edgeIndex].data.markerEnd = 'none';
        } else if (relationshipType === 'one-to-many') {
            diagram[edgeIndex].data.markerStart = 'none';
            diagram[edgeIndex].data.markerEnd = 'url(#chickenFoot)';
        } else if (relationshipType === 'many-to-many') {
            diagram[edgeIndex].data.markerStart = 'url(#chickenFoot)';
            diagram[edgeIndex].data.markerEnd = 'url(#chickenFoot)';
        }
    }
    },
    addEdge(diagramRef, sourceId, targetId) {
        diagramRef.value.push({
            id: Math. floor(Math. random() * 100).toString(),
            type: 'chickenFoot',
            source: sourceId,
            target: targetId,
        })
    },

    deleteEdge(diagramRef, selectedEdgeRef) {
    const diagram = diagramRef.value;
    const selectedEdge = selectedEdgeRef.value;

        diagramRef.value = diagram.filter(el => el.id !== selectedEdge.id);
    },

    deleteNode(diagramRef, nodeId) {

    const diagram = diagramRef.value;
    const nodeToDelete = diagram.find(el => el.id === nodeId);

    if (nodeToDelete.type === 'table') {
        diagramRef.value = diagram.filter(el => el.id !== nodeId && (el.type !== 'row' || el.parentNode !== nodeId));
    } else {
        diagramRef.value = diagram.filter(el => el.id !== nodeId);
    }

    if (nodeToDelete.type === 'row') {
        const siblingRows = diagram.filter(
            el => el.parentNode === nodeToDelete.parentNode && el.position.y > nodeToDelete.position.y);

        siblingRows.forEach((row, index) => {
            row.position.y = nodeToDelete.position.y + 40 * index;
        });
    }
    },
}

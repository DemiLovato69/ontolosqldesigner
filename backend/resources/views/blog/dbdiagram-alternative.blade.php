@extends('layouts.main')

@section('title', 'dbdiagram.io Alternative — Free with SQL Export')

@section('head')
    <meta name="description" content="Looking for a free dbdiagram alternative? SQL Designer offers unlimited diagrams, free SQL export, and a visual drag-and-drop canvas — no paywall.">
    <meta name="author" content="Dmitriy Snyatkov">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://sql-designer.com/blog/dbdiagram-alternative">
    <meta property="og:title" content="dbdiagram.io Alternative — Free ERD Tool with SQL Export">
    <meta property="og:description" content="dbdiagram.io locks SQL export and private diagrams behind a paywall. SQL Designer is a free alternative with visual drag-and-drop, unlimited diagrams, and free DDL export for MySQL and PostgreSQL.">
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://sql-designer.com/blog/dbdiagram-alternative">
    <meta property="og:image" content="https://sql-designer.com/images/designer_screenshot.png">
    <meta property="og:image:width" content="2556">
    <meta property="og:image:height" content="1271">
    <meta property="og:image:alt" content="SQL Designer — free visual database schema designer for MySQL and PostgreSQL">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="dbdiagram.io Alternative — Free ERD Tool with SQL Export">
    <meta name="twitter:description" content="dbdiagram.io locks SQL export and private diagrams behind a paywall. SQL Designer is a free alternative with a visual canvas and unlimited DDL export.">
    <meta name="twitter:image" content="https://sql-designer.com/images/designer_screenshot.png">
    <script type="application/ld+json">
        @verbatim
        [
        {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://sql-designer.com/" },
                { "@type": "ListItem", "position": 2, "name": "Blog", "item": "https://sql-designer.com/blog" },
                { "@type": "ListItem", "position": 3, "name": "dbdiagram.io Alternative", "item": "https://sql-designer.com/blog/dbdiagram-alternative" }
            ]
        },
        {
            "@context": "https://schema.org",
            "@type": "TechArticle",
            "headline": "dbdiagram.io Alternative — Free ERD Tool with Visual Canvas and SQL Export",
            "description": "dbdiagram.io locks SQL export and private diagrams behind a paywall. This guide covers the best free dbdiagram alternatives, with SQL Designer as the top visual option.",
            "image": "https://sql-designer.com/images/designer_screenshot.png",
            "url": "https://sql-designer.com/blog/dbdiagram-alternative",
            "datePublished": "2026-05-15",
            "dateModified": "2026-05-15",
            "author": { "@type": "Person", "name": "Dmitriy Snyatkov", "url": "https://sql-designer.com/about", "sameAs": "https://github.com/Snydi", "worksFor": { "@type": "Organization", "name": "SQL Designer", "url": "https://sql-designer.com" } },
            "publisher": { "@type": "Organization", "name": "SQL Designer", "url": "https://sql-designer.com", "sameAs": "https://github.com/Snydi/sqldesigner", "logo": { "@type": "ImageObject", "url": "https://sql-designer.com/favicon-192x192.png" } },
            "speakable": { "@type": "SpeakableSpecification", "cssSelector": [".intro"] },
            "mainEntityOfPage": { "@type": "WebPage", "@id": "https://sql-designer.com/blog/dbdiagram-alternative" }
        },
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": "What is the best free dbdiagram.io alternative?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "SQL Designer is the strongest free dbdiagram.io alternative for visual schema design. Unlike dbdiagram.io, SQL Designer offers a drag-and-drop canvas (no DSL required), free SQL export for MySQL, PostgreSQL, SQLite, Oracle, SQL Server, and MS Access, unlimited diagrams, and private diagrams by default — all at no cost with no credit card required."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Why do people look for dbdiagram.io alternatives?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "The most common reasons are: (1) SQL export is paywalled on the free tier — you can draw diagrams but cannot generate MySQL or PostgreSQL DDL without paying; (2) diagrams are public by default — private diagrams require a paid plan; (3) the interface is text-first (DBML syntax), which adds friction for developers who prefer a visual canvas; (4) real-time collaboration is a paid feature."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Does SQL Designer export SQL for free?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes. SQL Designer exports CREATE TABLE scripts for MySQL, PostgreSQL, SQLite, Oracle, SQL Server, and MS Access — all for free with no tier restrictions. There is no paywall on SQL export. You can also import an existing SQL script to visualize it as a diagram."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Is SQL Designer visual like dbdiagram.io?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "SQL Designer is more visual than dbdiagram.io. dbdiagram.io requires you to define your schema in DBML (a text DSL) and renders a diagram on the right. SQL Designer uses a drag-and-drop canvas: you create tables by clicking, add columns with type dropdowns, and draw foreign key relationships by connecting columns — no text syntax required. Both tools can visualize a schema, but SQL Designer is the more visual-first option."
                    }
                },
                {
                    "@type": "Question",
                    "name": "What are the main differences between SQL Designer and dbdiagram.io?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Key differences: SQL Designer has a visual drag-and-drop canvas; dbdiagram.io is text-first with DBML. SQL Designer exports SQL for free on all six dialects; dbdiagram.io paywalls SQL export. SQL Designer diagrams are private by default; dbdiagram.io makes diagrams public unless you pay. SQL Designer includes real-time collaboration at no cost; dbdiagram.io charges for collaboration features. Both tools are browser-based and free to start."
                    }
                }
            ]
        }
        ]
        @endverbatim
    </script>
    <style>
        body { overflow-y: auto; }

        .blog-post {
            max-width: 760px;
            margin: 0 auto;
            padding: 3rem 1.5rem 5rem;
        }

        .blog-post .breadcrumb {
            font-size: 0.875rem;
            color: var(--text-muted);
            background-color: transparent;
            text-transform: none;
            margin-bottom: 1.5rem;
        }

        .blog-post .breadcrumb a { color: var(--color-primary-text); }

        .blog-post .post-meta {
            font-size: 0.875rem;
            color: var(--text-muted);
            background-color: transparent;
            text-transform: none;
            margin-bottom: 1rem;
        }

        .blog-post h1 {
            font-size: 1.6rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-primary);
            background-color: transparent;
            margin: 0 0 1rem;
            line-height: 1.3;
        }

        .blog-post .intro {
            font-size: 1rem;
            color: var(--text-secondary);
            background-color: transparent;
            text-transform: none;
            line-height: 1.8;
            margin-bottom: 2.5rem;
            border-left: 3px solid var(--color-primary-text);
            padding-left: 1.2rem;
        }

        .blog-post h2 {
            font-size: 1.05rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-primary-text);
            background-color: transparent;
            margin: 2.5rem 0 0.8rem;
        }

        .blog-post h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 1.5rem 0 0.5rem;
            letter-spacing: 0;
            text-transform: none;
        }

        .blog-post p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            background-color: transparent;
            text-transform: none;
            line-height: 1.8;
            margin: 0 0 1rem;
        }

        .blog-post ul, .blog-post ol {
            margin: 0 0 1rem 1.5rem;
            padding: 0;
        }

        .blog-post li {
            font-size: 0.9rem;
            color: var(--text-secondary);
            background-color: transparent;
            text-transform: none;
            line-height: 1.8;
            margin-bottom: 0.3rem;
        }

        .blog-post code {
            background: var(--bg-elevated);
            padding: 0.1em 0.4em;
            border-radius: 3px;
            font-size: 0.85em;
            color: var(--text-primary);
        }

        .blog-post .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0 2rem;
            font-size: 0.82rem;
            display: block;
            overflow-x: auto;
        }

        .blog-post .comparison-table th {
            background: var(--bg-elevated);
            color: var(--text-primary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.6rem 0.8rem;
            text-align: left;
            border-bottom: 2px solid var(--border-strong);
            white-space: nowrap;
        }

        .blog-post .comparison-table td {
            padding: 0.55rem 0.8rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            vertical-align: top;
        }

        .blog-post .comparison-table tr:last-child td { border-bottom: none; }

        .blog-post .check  { color: #16a34a; font-weight: bold; }
        .blog-post .cross  { color: #dc2626; font-weight: bold; }
        .blog-post .partial { color: #d97706; font-weight: bold; }

        .blog-post .faq-item { margin-bottom: 1.8rem; }

        .blog-post .faq-item h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            background-color: transparent;
            text-transform: none;
            margin: 0 0 0.4rem;
            letter-spacing: 0;
        }

        .blog-post .alt-card {
            background: var(--bg-surface);
            border-radius: 6px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1rem;
            border-left: 3px solid var(--color-primary-text);
        }

        .blog-post .alt-card h3 {
            font-size: 0.9rem;
            text-transform: none;
            font-weight: 600;
            color: var(--color-primary-text);
            margin: 0 0 0.5rem;
            letter-spacing: 0;
        }

        .blog-post .alt-card p {
            margin: 0 0 0.4rem;
            font-size: 0.85rem;
        }

        .blog-post .alt-card p:last-child { margin-bottom: 0; }

        .blog-post .cta-box {
            background: var(--color-primary-hover);
            color: #fff;
            border-radius: 6px;
            padding: 2rem;
            text-align: center;
            margin-top: 3rem;
        }

        .blog-post .cta-box h3 {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 0.8rem;
        }

        .blog-post .cta-box p {
            color: #fff;
            background-color: transparent;
            margin: 0 0 1.2rem;
            font-size: 0.85rem;
        }

        .blog-post .btn-cta {
            background: var(--bg-surface);
            color: var(--color-primary-text);
            padding: 0.6rem 1.8rem;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-decoration: none;
        }

        .blog-post .btn-cta:hover { opacity: 0.9; }
    </style>
@endsection

@section('content')
    <article class="blog-post">
        <p class="breadcrumb"><a href="/blog">Blog</a> &rsaquo; Tools</p>
        <p class="post-meta"><time datetime="2026-05-15">May 2026</time> &mdash; by <a href="/about" style="color:var(--color-primary-text);">Dmitriy Snyatkov</a> &mdash; 9 min read</p>
        <h1>dbdiagram.io Alternative — Free ERD Tool with Visual Canvas and SQL Export</h1>

        <p class="intro">
            dbdiagram.io is one of the most-used database schema tools — but its free tier has significant restrictions that push many developers to look for alternatives. SQL export requires a paid plan. Diagrams are public by default; private storage costs money. Real-time collaboration is paywalled. For teams that want to design a database schema, export valid SQL, and keep their work private — without paying — the free tier is not enough. This guide covers the best free dbdiagram.io alternatives, what each is good at, and where to start.
        </p>

        <p>
            <strong>Disclosure:</strong> SQL Designer is our product. We rank it first because we believe it addresses the
            specific gaps that drive people to look for dbdiagram alternatives — particularly free SQL export and a visual
            canvas. Read the other options and judge for yourself.
        </p>

        <h2>Why Developers Look for dbdiagram.io Alternatives</h2>
        <p>dbdiagram.io has a loyal following for good reasons: the DBML syntax is fast for developers who prefer typing a schema over clicking, the visual output is clean, and the sharing workflow is simple. But the paywall creates real friction:</p>

        <ul>
            <li><strong>SQL export is not free.</strong> You can draw and share a diagram for free, but generating MySQL, PostgreSQL, or SQL Server DDL requires a paid plan ($9/month). This is the most common pain point — designers who need to turn their diagram into runnable SQL hit a wall.</li>
            <li><strong>Private diagrams cost money.</strong> On the free plan, all diagrams are publicly accessible. Anyone with the URL can view your schema. Private diagrams require upgrading.</li>
            <li><strong>Text-first interface is not for everyone.</strong> dbdiagram.io requires you to write DBML — a schema definition language — to create diagrams. Developers who prefer a visual, click-based workflow often find this adds unnecessary friction.</li>
            <li><strong>Real-time collaboration is paywalled.</strong> Sharing a static view is free; multiple people editing the same diagram simultaneously is a paid feature.</li>
            <li><strong>No visual canvas editing.</strong> You cannot drag tables, resize columns, or reposition elements with a mouse — everything is driven by the DBML text input.</li>
        </ul>

        <h2>Quick Comparison</h2>
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>dbdiagram.io (free)</th>
                    <th>SQL Designer (free)</th>
                    <th>DrawSQL (free)</th>
                    <th>QuickDBD (free)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Visual drag-and-drop canvas</td>
                    <td class="cross">✗ (text-only)</td>
                    <td class="check">✓</td>
                    <td class="check">✓</td>
                    <td class="cross">✗ (text-only)</td>
                </tr>
                <tr>
                    <td>SQL export on free tier</td>
                    <td class="cross">✗ (paid)</td>
                    <td class="check">✓ 6 dialects</td>
                    <td class="check">✓ several</td>
                    <td class="check">✓</td>
                </tr>
                <tr>
                    <td>Private diagrams (free)</td>
                    <td class="cross">✗ (paid)</td>
                    <td class="check">✓</td>
                    <td class="check">✓</td>
                    <td class="partial">~</td>
                </tr>
                <tr>
                    <td>Unlimited diagrams</td>
                    <td class="partial">~ (public only)</td>
                    <td class="check">✓</td>
                    <td class="cross">✗ (~15 tables/diagram)</td>
                    <td class="cross">✗ (1 diagram max)</td>
                </tr>
                <tr>
                    <td>Real-time collaboration</td>
                    <td class="cross">✗ (paid)</td>
                    <td class="check">✓</td>
                    <td class="partial">~ (limited)</td>
                    <td class="cross">✗</td>
                </tr>
                <tr>
                    <td>SQL import (reverse-engineer)</td>
                    <td class="check">✓ (DBML)</td>
                    <td class="check">✓ (SQL scripts)</td>
                    <td class="cross">✗</td>
                    <td class="partial">~</td>
                </tr>
                <tr>
                    <td>Starting price</td>
                    <td>$9/mo</td>
                    <td>Free</td>
                    <td>$15/mo</td>
                    <td>$14/mo</td>
                </tr>
            </tbody>
        </table>

        <h2>The Best Free dbdiagram.io Alternatives</h2>

        <div class="alt-card">
            <h3>1. SQL Designer — sql-designer.com (Best for visual design + free SQL export)</h3>
            <p>SQL Designer is a browser-based ERD tool built for developers who want to design a relational database schema visually and export real SQL — without paying. It covers the two main gaps in dbdiagram.io's free tier: SQL export is free on all six dialects (MySQL, PostgreSQL, SQLite, Oracle, SQL Server, MS Access), and all diagrams are private by default.</p>
            <p>The design experience is visual: drag tables onto a canvas, add columns with database type dropdowns, set PRIMARY KEY, UNIQUE, and NOT NULL constraints by clicking, and draw foreign key relationships by connecting columns with your mouse. The diagram renders crow's foot notation for cardinality. When the schema is ready, one click exports a CREATE TABLE DDL script for your target engine. If you have an existing SQL script you want to visualize, you can import it directly and the diagram generates automatically.</p>
            <p>Where SQL Designer differs most from dbdiagram.io: there is no text DSL to learn. If you find DBML friction — having to look up syntax for constraints, foreign keys, or enums — SQL Designer removes that barrier entirely. The tradeoff is that experienced DBML users may find clicking slower than typing.</p>
            <p><strong>Free tier:</strong> unlimited diagrams, unlimited tables, SQL export on all six dialects, private diagrams, real-time collaboration, shareable links, embeddable iframes. No credit card required. The <a href="/demo" style="color:var(--color-primary-text);">demo canvas</a> works without an account.</p>
            <p><strong>Limitation:</strong> no live database connection for auto-generating diagrams (you import SQL scripts, not live databases).</p>
        </div>

        <div class="alt-card">
            <h3>2. DrawSQL — drawsql.app (Best for visual design with broader database support)</h3>
            <p>DrawSQL is a polished visual database schema designer that supports MySQL, PostgreSQL, SQLite, and SQL Server. The interface is drag-and-drop with clean rendering and SQL export on the free tier. Team collaboration — sharing and commenting — is included.</p>
            <p><strong>Free tier limitation:</strong> diagrams are capped at approximately 15 tables. For small schemas this is workable; for real-world projects with 20–50 tables, you hit the limit quickly. Paid plans start at $15/month. If the table cap is not a concern for your use case, DrawSQL is a strong visual alternative to dbdiagram.io.</p>
        </div>

        <div class="alt-card">
            <h3>3. QuickDBD — quickdatabasediagrams.com (Best for fast text-to-diagram with SQL export)</h3>
            <p>QuickDBD is the closest text-first alternative to dbdiagram.io. Like dbdiagram.io, it accepts a text schema definition and renders a clean visual diagram. Unlike dbdiagram.io, SQL export is included on the free tier for MySQL, PostgreSQL, SQL Server, and others.</p>
            <p><strong>Free tier limitation:</strong> you can only create one diagram on the free plan. Multiple schemas require upgrading at $14/month. If you only need a single diagram and want SQL export without paying, QuickDBD is the best text-driven alternative.</p>
        </div>

        <div class="alt-card">
            <h3>4. ChartDB — chartdb.io (Best for documenting an existing schema)</h3>
            <p>ChartDB is an open-source tool primarily designed for importing and understanding existing databases. Paste a SQL script or connect to a live database, and ChartDB generates a visual schema with AI-assisted explanations. It exports DDL for free and is MIT-licensed. If your goal is to document or reverse-engineer a database rather than design a new one from scratch, ChartDB is the strongest free option.</p>
            <p><strong>Free tier limitation:</strong> AI features require an API key or the cloud paid plan. The cloud version starts at $12.5/month; self-hosting is free with no restrictions.</p>
        </div>

        <h2>When to Stay with dbdiagram.io</h2>
        <p>If your team already has DBML expertise and your diagrams are intentionally public (open-source projects, documentation), dbdiagram.io's free tier is genuinely useful. The DBML syntax is well-documented and the diagram output is clean. For teams working on internal schemas who need SQL export and private storage, the free tier requires an upgrade.</p>

        <h2>Migrating from dbdiagram.io to SQL Designer</h2>
        <p>If you have an existing dbdiagram.io schema and want to move to SQL Designer, the quickest path is:</p>
        <ol>
            <li>In dbdiagram.io, use the <strong>Export to SQL</strong> option — if you have a paid plan — to generate a MySQL or PostgreSQL CREATE TABLE script. If you do not have the export, you can write the DDL manually from the DBML definition, or use the DBML-to-SQL converter in the dbdiagram.io docs.</li>
            <li>In SQL Designer, click <strong>Import</strong> and paste the CREATE TABLE script. SQL Designer parses the SQL and generates the visual diagram automatically — tables, columns, data types, constraints, and foreign key relationships.</li>
            <li>Review the diagram, adjust table positions on the canvas, and export fresh DDL for your target engine when ready.</li>
        </ol>
        <p>The process takes a few minutes for a typical schema. You can try it immediately on the <a href="/demo" style="color:var(--color-primary-text);">demo canvas</a> without creating an account.</p>

        <h2>Frequently Asked Questions</h2>

        <div class="faq-item">
            <h3>What is the best free dbdiagram.io alternative?</h3>
            <p>SQL Designer is the strongest free alternative for visual database design with SQL export. It removes the two most common objections to dbdiagram.io's free tier — paywalled SQL export and public-only diagrams — and replaces the text DSL with a drag-and-drop canvas. For teams who prefer text-first workflows, QuickDBD offers free SQL export on one diagram. For documenting an existing database, ChartDB is the best free option.</p>
        </div>

        <div class="faq-item">
            <h3>Why do people look for dbdiagram.io alternatives?</h3>
            <p>The most common reasons: SQL export is locked behind a paid plan on the free tier, diagrams default to public visibility (private diagrams cost $9/month), the text-only DBML interface adds friction for non-developer stakeholders, and real-time collaboration is a paid feature. Teams that need SQL export and private storage without paying typically find the free tier insufficient.</p>
        </div>

        <div class="faq-item">
            <h3>Does SQL Designer export SQL for free?</h3>
            <p>Yes. SQL Designer exports complete CREATE TABLE DDL scripts for MySQL, PostgreSQL, SQLite, Oracle, SQL Server, and MS Access on the free tier — with no restrictions and no credit card required. You can also import an existing SQL script to convert it into a visual diagram. See the <a href="/blog/sql-to-erd" style="color:var(--color-primary-text);">SQL to ERD guide</a> for details on the import workflow.</p>
        </div>

        <div class="faq-item">
            <h3>Is SQL Designer visual like dbdiagram.io?</h3>
            <p>SQL Designer is more visual than dbdiagram.io. dbdiagram.io requires DBML — a text schema language — to build a diagram. SQL Designer uses a drag-and-drop canvas where you create tables by clicking, add columns with type dropdowns, and draw foreign key relationships by connecting columns. No text syntax is required, though you can import existing SQL scripts if you prefer to start from code.</p>
        </div>

        <div class="faq-item">
            <h3>What are the main differences between SQL Designer and dbdiagram.io?</h3>
            <p>
                The key differences: SQL Designer is visual-first (drag-and-drop canvas); dbdiagram.io is text-first (DBML). SQL Designer exports SQL free on six dialects; dbdiagram.io paywalls SQL export. SQL Designer diagrams are private by default; dbdiagram.io defaults to public. SQL Designer includes real-time collaboration at no cost; dbdiagram.io charges for it. Both are browser-based and free to start.
            </p>
            <p>See the full comparison of ten tools including both in our <a href="/blog/best-free-erd-tools" style="color:var(--color-primary-text);">10 Best Free ERD Tools guide</a>.</p>
        </div>

        <nav aria-label="Related articles" style="margin-top:3rem; padding-top:2rem; border-top:1px solid var(--border-color);">
            <p style="font-size:0.875rem; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin:0 0 0.8rem;">Related Articles</p>
            <ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:0.5rem;">
                <li><a href="/blog/best-free-erd-tools" style="color:var(--color-primary-text); font-size:0.88rem; text-decoration:none;">10 Best Free ERD Tools in 2026 — Tested and Compared &rarr;</a></li>
                <li><a href="/blog/free-erd-tool" style="color:var(--color-primary-text); font-size:0.88rem; text-decoration:none;">Free ERD Tool Online — Visual Entity Relationship Diagram Editor &rarr;</a></li>
                <li><a href="/blog/sql-to-erd" style="color:var(--color-primary-text); font-size:0.88rem; text-decoration:none;">SQL to ERD — Generate a Diagram from a SQL Script &rarr;</a></li>
                <li><a href="/blog/how-to-design-mysql-database-schema" style="color:var(--color-primary-text); font-size:0.88rem; text-decoration:none;">How to Design a MySQL Database Schema — Step-by-Step Guide &rarr;</a></li>
            </ul>
        </nav>

        <div class="cta-box">
            <h3>Try SQL Designer — free, no install</h3>
            <p>Visual drag-and-drop schema design for MySQL, PostgreSQL, SQLite, Oracle, SQL Server, and MS Access. Free SQL export, unlimited diagrams, private by default, real-time collaboration. No credit card, no paywall.</p>
            <a class="btn-cta" href="/demo">Try the Demo</a>
        </div>
    </article>
@endsection

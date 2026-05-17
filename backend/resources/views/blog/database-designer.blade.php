@extends('layouts.main')

@section('title', 'Free DB Designer Online — Visual Database Designer')

@section('head')
    <meta name="description"
          content="Free DB designer — design relational database schemas visually with drag-and-drop tables, foreign keys, and SQL export for MySQL and PostgreSQL.">
    <meta name="author" content="Dmitriy Snyatkov">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://sql-designer.com/blog/database-designer">
    <meta property="og:title" content="Free DB Designer Online — Visual Database Designer">
    <meta property="og:description"
          content="Free DB designer — design relational schemas visually in your browser. Drag-and-drop tables, foreign keys, MySQL and PostgreSQL SQL export. No install.">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="SQL Designer">
    <meta property="og:url" content="https://sql-designer.com/blog/database-designer">
    <meta property="og:image" content="https://sql-designer.com/images/designer_screenshot.png">
    <meta property="og:image:width" content="2557">
    <meta property="og:image:height" content="1269">
    <meta property="og:image:alt" content="SQL Designer — free online database designer">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Free DB Designer Online — Visual Database Designer">
    <meta name="twitter:description" content="Free DB designer for MySQL and PostgreSQL — design schemas visually with drag-and-drop tables and SQL export. No install.">
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
                { "@type": "ListItem", "position": 3, "name": "Free Online Database Designer", "item": "https://sql-designer.com/blog/database-designer" }
            ]
        },
        {
            "@context": "https://schema.org",
            "@type": "TechArticle",
            "headline": "Free DB Designer Online — Visual Database Designer",
            "description": "Free DB designer — design relational database schemas visually with drag-and-drop tables, foreign keys, and SQL export for MySQL and PostgreSQL.",
            "image": "https://sql-designer.com/images/designer_screenshot.png",
            "url": "https://sql-designer.com/blog/database-designer",
            "datePublished": "2026-04-09",
            "dateModified": "2026-05-16",
            "author": { "@type": "Person", "name": "Dmitriy Snyatkov", "url": "https://sql-designer.com/about", "sameAs": "https://github.com/Snydi", "worksFor": { "@type": "Organization", "name": "SQL Designer", "url": "https://sql-designer.com" } },
            "publisher": { "@type": "Organization", "name": "SQL Designer", "url": "https://sql-designer.com", "sameAs": "https://github.com/Snydi/sqldesigner", "logo": { "@type": "ImageObject", "url": "https://sql-designer.com/favicon-192x192.png" } },
            "speakable": { "@type": "SpeakableSpecification", "cssSelector": [".intro"] },
            "mainEntityOfPage": { "@type": "WebPage", "@id": "https://sql-designer.com/blog/database-designer" }
        },
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": "What is an online database designer tool?",
                    "acceptedAnswer": { "@type": "Answer", "text": "An online database designer is a browser-based tool for planning relational database schemas visually. You add tables to a canvas, define columns with data types and constraints, draw foreign key relationships between tables, and export a CREATE TABLE SQL script — without writing DDL by hand." }
                },
                {
                    "@type": "Question",
                    "name": "What does a database designer tool actually output?",
                    "acceptedAnswer": { "@type": "Answer", "text": "Most database designer tools output a SQL DDL script — a set of CREATE TABLE statements you can run directly against a MySQL or PostgreSQL database. Some also allow exporting a diagram image or sharing a read-only link." }
                },
                {
                    "@type": "Question",
                    "name": "Can I use a database designer tool without installing anything?",
                    "acceptedAnswer": { "@type": "Answer", "text": "Yes. Browser-based database designer tools run entirely in your browser — there is nothing to download or install. You create a free account and start designing immediately from any device." }
                },
                {
                    "@type": "Question",
                    "name": "What is the difference between a database designer and a generic diagram tool?",
                    "acceptedAnswer": { "@type": "Answer", "text": "A generic diagram tool (like draw.io or Figma) lets you draw boxes and lines but doesn't understand SQL. A purpose-built database designer knows your column types, validates constraints, and can generate a correct CREATE TABLE script. The diagram and the DDL are kept in sync." }
                },
                {
                    "@type": "Question",
                    "name": "Do free database designer tools have limits on diagrams or tables?",
                    "acceptedAnswer": { "@type": "Answer", "text": "Some tools limit free accounts to a small number of diagrams or tables per diagram. Others, like SQL Designer, are fully free with no diagram count limits, no table limits, and no SQL export paywall." }
                }
            ]
        },
        {
            "@context": "https://schema.org",
            "@type": "SoftwareApplication",
            "name": "SQL Designer",
            "url": "https://sql-designer.com",
            "applicationCategory": "DeveloperApplication",
            "operatingSystem": "Web",
            "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
            "description": "Free online database designer for MySQL and PostgreSQL. Plan and visualise relational database schemas with a drag-and-drop canvas, real SQL data types and constraints, visual foreign key relationship lines, and one-click CREATE TABLE SQL export. No installation or credit card required.",
            "featureList": ["MySQL and PostgreSQL support", "Visual drag-and-drop canvas", "SQL export", "SQL import", "Foreign key relationships", "Real-time collaboration", "Shareable diagram links", "No diagram or table limits"]
        }
        ]
        @endverbatim
    </script>
    <style>
        body { overflow-y: auto; }
        .blog-post { max-width: 760px; margin: 0 auto; padding: 3rem 1.5rem 5rem; }
        .blog-post .breadcrumb { font-size: 0.875rem; color: #767676; background-color: transparent; text-transform: none; margin-bottom: 1.5rem; }
        .blog-post .breadcrumb a { color: var(--color-primary); }
        .blog-post .post-meta { font-size: 0.875rem; color: #767676; background-color: transparent; text-transform: none; margin-bottom: 1rem; }
        .blog-post h1 { font-size: 1.6rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-primary); background-color: transparent; margin: 0 0 1rem; line-height: 1.3; }
        .blog-post .intro { font-size: 1rem; color: var(--text-secondary); background-color: transparent; text-transform: none; line-height: 1.8; margin-bottom: 2rem; border-left: 3px solid var(--color-primary); padding-left: 1.2rem; }
        .blog-post h2 { font-size: 1.05rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); background-color: transparent; margin: 2.5rem 0 0.8rem; }
        .blog-post p { font-size: 0.9rem; color: var(--text-secondary); background-color: transparent; text-transform: none; line-height: 1.8; margin: 0 0 1rem; }
        .blog-post ul { margin: 0 0 1rem 1.5rem; padding: 0; }
        .blog-post li { font-size: 0.9rem; color: var(--text-secondary); background-color: transparent; text-transform: none; line-height: 1.8; margin-bottom: 0.3rem; }
        .blog-post code { background: var(--bg-elevated); padding: 0.1em 0.4em; border-radius: 3px; font-size: 0.85em; color: var(--text-primary); }
        .blog-post .cta-box { background: var(--color-primary-hover); color: #fff; border-radius: 6px; padding: 2rem; text-align: center; margin-top: 3rem; }
        .blog-post .cta-box h3 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 0.8rem; }
        .blog-post .cta-box p { color: #fff; background-color: transparent; margin: 0 0 1.2rem; font-size: 0.85rem; }
        .blog-post .btn-cta { background: var(--bg-surface); color: var(--color-primary); padding: 0.6rem 1.8rem; border-radius: 4px; font-weight: bold; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; text-decoration: none; }
        .blog-post .btn-cta:hover { opacity: 0.9; }
        .blog-post .key-takeaways { background: var(--bg-elevated); border-left: 3px solid var(--color-primary); border-radius: 4px; padding: 1.2rem 1.5rem; margin-bottom: 2rem; }
        .blog-post .kt-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-primary); margin: 0 0 0.7rem; font-weight: 700; }
        .blog-post .key-takeaways ul { margin: 0 0 0 1rem; }
        .blog-post .key-takeaways li { font-size: 0.88rem; margin-bottom: 0.4rem; }
        .blog-post figure { margin: 1.5rem 0; }
        .blog-post figcaption { font-size: 0.78rem; color: #767676; margin-top: 0.4rem; text-align: center; }
        .blog-post figure svg { border-radius: 6px; width: 100%; height: auto; display: block; }
        .blog-post figure img { width: 100%; border-radius: 6px; display: block; }
        .blog-post .citation-capsule { background: var(--bg-elevated); border-radius: 4px; padding: 1rem 1.2rem; margin: 1.2rem 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.7; border-left: 2px solid var(--border-color); font-style: italic; }
        .blog-post .faq-section { margin-top: 3rem; }
        .blog-post .faq-item { border-top: 1px solid var(--border-color); padding: 1.2rem 0; }
        .blog-post .faq-item:last-child { border-bottom: 1px solid var(--border-color); }
        .blog-post .faq-item h3 { font-size: 0.93rem; text-transform: none; letter-spacing: 0; color: var(--text-primary); background-color: transparent; margin: 0 0 0.5rem; font-weight: 600; }
        .blog-post .faq-item p { margin: 0; }
    </style>
@endsection

@section('content')
    <article class="blog-post">
        <p class="breadcrumb"><a href="/blog">Blog</a> &rsaquo; Schema Design</p>
        <p class="post-meta"><time datetime="2026-04-09">April 2026</time> &mdash; <time datetime="2026-05-16">Last updated: May 2026</time> &mdash; by <a href="/about" style="color:var(--color-primary-text);">Dmitriy Snyatkov</a> &mdash; 7 min read</p>
        <h1>Free DB Designer Online — Visual Database Designer for MySQL &amp; PostgreSQL</h1>

        <p class="intro">
            A database designer is a browser-based tool for planning relational schemas: add tables to a canvas, define columns with SQL data types and constraints, draw foreign key relationships, and export a <code>CREATE TABLE</code> DDL script for MySQL or PostgreSQL. No writing DDL by hand. This guide covers what separates a purpose-built database designer from a generic diagram tool and what to look for in a free one.
        </p>

        <div class="key-takeaways">
            <p class="kt-title">Key Takeaways</p>
            <ul>
                <li>64% of organizations actively use data modeling in 2024, up from 51% in 2023 (<a href="https://www.dataversity.net/articles/data-modeling-trends-in-2025-simplifying-complex-business-problems/" style="color:var(--color-primary);">Dataversity 2024</a>)</li>
                <li>PostgreSQL is used by 51.9% of professional developers and MySQL by 39.4% — a good designer must support both type systems (<a href="https://survey.stackoverflow.co/2024/technology" style="color:var(--color-primary);">Stack Overflow 2024</a>)</li>
                <li>A purpose-built database designer exports runnable SQL DDL; a generic diagram tool (draw.io, Figma) exports only an image</li>
            </ul>
        </div>

        <figure>
            <img src="https://images.unsplash.com/photo-1597138768744-9f97be8cdd64?fm=jpg&q=80&w=1200&h=630&fit=crop"
                 alt="Server hardware in a data center, representing database infrastructure"
                 loading="lazy">
            <figcaption>Photo: Marc PEZIN / Unsplash</figcaption>
        </figure>

        <h2>What Does a Database Designer Do?</h2>
        <p>
            Data modeling adoption hit 64% of organizations in 2024, up from 51% the year before. That's a 13-point jump reflecting how many teams have moved from ad hoc DDL toward structured visual schema planning (<a href="https://www.dataversity.net/articles/data-modeling-trends-in-2025-simplifying-complex-business-problems/" style="color:var(--color-primary);">Dataversity Trends in Data Management 2024</a>). A database designer is the tool that makes that planning concrete.
        </p>
        <p>The core workflow:</p>
        <ul>
            <li>Add tables to a canvas, one per entity in your data model</li>
            <li>Define columns: name, data type, constraints</li>
            <li>Draw foreign key relationships between tables</li>
            <li>Export the schema as a SQL <code>CREATE TABLE</code> script</li>
        </ul>
        <p>
            The canvas gives you a full view of your schema at once. You can see how tables relate, spot missing relationships, and reason about structure without reading walls of DDL. It's also shareable — paste a link and anyone on the team can see exactly what you're designing.
        </p>
        <div class="citation-capsule">
            Data modeling adoption reached 64% of organizations in 2024, up from 51% in 2023, according to the Dataversity Trends in Data Management 2024 report. That 13-point year-over-year increase reflects growing recognition that visual schema planning cuts design errors and speeds developer onboarding on complex relational systems.
        </div>

        <h2>Who Uses an Online Database Designer?</h2>
        <p>
            79% of IT teams now run more than one database platform, up from 62% in 2020 (<a href="https://www.red-gate.com/solutions/state-of-database-landscape/2024/" style="color:var(--color-primary);">Redgate State of the Database Landscape 2024</a>, n=3,849). That multi-platform reality is why a visual canvas matters. When your schema spans a MySQL transactional store and a PostgreSQL analytics database, a shared diagram is far easier to discuss than two separate DDL files.
        </p>
        <ul>
            <li><strong>Backend developers</strong> planning a new service that needs database tables</li>
            <li><strong>Students</strong> learning relational modelling and entity-relationship diagrams</li>
            <li><strong>DBAs</strong> documenting an existing schema or planning a redesign</li>
            <li><strong>Freelancers</strong> designing a client database quickly, without installing heavy tools</li>
            <li><strong>Teams</strong> reviewing a schema together — a diagram is far easier to discuss than DDL text</li>
        </ul>
        <div class="citation-capsule">
            According to Redgate's State of the Database Landscape 2024 (n=3,849 respondents across six continents), 79% of IT teams now run more than one database platform, up from 62% in 2020. Schema decisions rarely live inside a single system anymore. Visual tools that support multiple dialects help teams see the full picture without switching contexts.
        </div>

        <h2>Free vs. Paid Database Designer Tools</h2>
        <p>
            Not all "free" database designer tools are actually free. Many lock key features behind a paid plan. Common restrictions on free tiers include:
        </p>
        <ul>
            <li>SQL export locked to paid tiers</li>
            <li>Limited tables per diagram (often 5&ndash;10 on free accounts)</li>
            <li>Private diagrams requiring a subscription</li>
            <li>Diagram count limits</li>
        </ul>
        <p>
            SQL Designer has none of these restrictions. It's completely free: unlimited diagrams, unlimited tables, full SQL export, no credit card required. Why pay a monthly fee just to download your own schema?
        </p>

        <h2>What to Look for in a Free Database Designer</h2>
        <p>
            PostgreSQL is now used by 51.9% of professional developers and MySQL by 39.4%, per the <a href="https://survey.stackoverflow.co/2024/technology" style="color:var(--color-primary);">Stack Overflow Developer Survey 2024</a> (n=65,437). A designer that doesn't properly support both type systems will produce invalid DDL for one of them. That's the baseline. Beyond database support, check for these features:
        </p>
        <ul>
            <li><strong>Support for your database</strong> — MySQL and PostgreSQL have different type systems; the tool must know which types are valid for each</li>
            <li><strong>Full constraint support</strong> — <code>PRIMARY KEY</code>, <code>UNIQUE</code>, <code>NOT NULL</code>, auto-increment</li>
            <li><strong>Visual foreign key lines</strong> — draw relationships between tables instead of writing constraint clauses</li>
            <li><strong>ERD notation</strong> — crow's foot notation shows cardinality (one-to-many, many-to-many)</li>
            <li><strong>SQL export</strong> — generate a valid <code>CREATE TABLE</code> DDL script directly from the diagram</li>
            <li><strong>Runs in the browser</strong> — no install, accessible from any device</li>
            <li><strong>Auto-save</strong> — work saved automatically, no manual save step</li>
        </ul>

        <figure>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 155" role="img"
                 aria-label="Horizontal bar chart showing PostgreSQL used by 51.9% and MySQL by 39.4% of professional developers in 2024">
                <rect width="560" height="155" fill="#111827" rx="6"/>
                <text x="280" y="22" text-anchor="middle" fill="#f3f4f6" font-family="system-ui,sans-serif" font-size="12" font-weight="600">Developer Database Usage — Professional Devs (2024)</text>
                <text x="10" y="59" fill="#9ca3af" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle">PostgreSQL</text>
                <rect x="118" y="46" width="182" height="22" fill="#22c55e" rx="2"/>
                <text x="306" y="59" fill="#f3f4f6" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle"> 51.9%</text>
                <text x="10" y="99" fill="#9ca3af" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle">MySQL</text>
                <rect x="118" y="86" width="138" height="22" fill="#3b82f6" rx="2"/>
                <text x="262" y="99" fill="#f3f4f6" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle"> 39.4%</text>
                <text x="280" y="143" text-anchor="middle" fill="#6b7280" font-family="system-ui,sans-serif" font-size="10">Source: Stack Overflow Developer Survey 2024 (n=65,437 professional developers)</text>
            </svg>
            <figcaption>PostgreSQL and MySQL together cover the majority of professional database workloads — your designer needs to handle both.</figcaption>
        </figure>

        <h2>SQL Designer — Free Online Database Designer</h2>
        <p>
            SQL Designer is a free online database designer for MySQL and PostgreSQL. The drag-and-drop canvas lets you add tables, define columns with the correct types for your target database, set constraints, and draw foreign key relationships. When the design is done, you export a clean <code>CREATE TABLE</code> SQL script.
        </p>
        <p>
            Everything runs in your browser. There's nothing to install. Create a free account with your email and start designing immediately. All diagrams are saved to your account and accessible from any device.
        </p>

        <h2>How to Design a Database with SQL Designer</h2>
        <p>The full process from blank canvas to runnable DDL takes five steps:</p>
        <ul>
            <li><strong>1. Create a diagram</strong> — sign up for free and start a new diagram. Name it to reflect the database or service you're designing.</li>
            <li><strong>2. Add tables</strong> — one table per entity. Common starting points: <code>users</code>, <code>products</code>, <code>orders</code>.</li>
            <li><strong>3. Define columns</strong> — for each column, set the name, data type (MySQL or PostgreSQL), and constraints (PK, UQ, NN).</li>
            <li><strong>4. Draw relationships</strong> — drag a line from the foreign key column to the primary key it references. Crow's foot notation is drawn automatically.</li>
            <li><strong>5. Export SQL</strong> — click the export button to download a complete, valid <code>CREATE TABLE</code> script for your chosen database.</li>
        </ul>
        <p>
            Not ready to sign up? The <a href="/demo" style="color:var(--color-primary);">demo</a> loads a sample schema so you can try the designer without creating an account.
        </p>

        <h2>Database Designer vs. Generic Diagram Tool</h2>
        <p>
            Tools like draw.io and Figma can produce something that looks like a database schema. But they're generic tools. They don't know what <code>VARCHAR(255)</code> means, they can't validate that a foreign key points to a primary key, and they can't generate SQL. They're fine for high-level conceptual sketches. They're the wrong tool for schemas you'll actually implement.
        </p>
        <p>
            Is the distinction really that important? Yes, if you've ever handed a draw.io diagram to a developer and asked them to implement it. Missing column types, absent constraints, and no SQL output can add hours of manual translation work that a proper designer eliminates entirely.
        </p>
        <p>
            A purpose-built database designer keeps the visual model and the SQL in sync. The diagram is the schema, not a picture of it. That's the difference that matters when you move from planning to building.
        </p>

        <figure>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 155" role="img"
                 aria-label="Bar chart showing organizations actively using data modeling grew from 51% in 2023 to 64% in 2024">
                <rect width="560" height="155" fill="#111827" rx="6"/>
                <text x="280" y="22" text-anchor="middle" fill="#f3f4f6" font-family="system-ui,sans-serif" font-size="12" font-weight="600">Organizations Actively Using Data Modeling</text>
                <text x="10" y="59" fill="#9ca3af" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle">2023</text>
                <rect x="80" y="46" width="179" height="22" fill="#6366f1" rx="2"/>
                <text x="265" y="59" fill="#f3f4f6" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle"> 51%</text>
                <text x="10" y="99" fill="#9ca3af" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle">2024</text>
                <rect x="80" y="86" width="224" height="22" fill="#22c55e" rx="2"/>
                <text x="310" y="99" fill="#f3f4f6" font-family="system-ui,sans-serif" font-size="12" dominant-baseline="middle"> 64%</text>
                <text x="355" y="99" fill="#22c55e" font-family="system-ui,sans-serif" font-size="11" dominant-baseline="middle">&#x2191; +13 pts YoY</text>
                <text x="280" y="143" text-anchor="middle" fill="#6b7280" font-family="system-ui,sans-serif" font-size="10">Source: Dataversity Trends in Data Management 2024</text>
            </svg>
            <figcaption>Data modeling adoption is growing fast — more teams are moving from ad hoc DDL to structured visual schema planning.</figcaption>
        </figure>

        <section class="faq-section">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-item">
                <h3>What is an online database designer tool?</h3>
                <p>An online database designer is a browser-based tool for planning relational database schemas visually. You add tables to a canvas, define columns with data types and constraints, draw foreign key relationships, and export a <code>CREATE TABLE</code> SQL script — without writing DDL by hand.</p>
            </div>
            <div class="faq-item">
                <h3>What does a database designer tool actually output?</h3>
                <p>Most database designer tools output a SQL DDL script — a set of <code>CREATE TABLE</code> statements you can run directly against a MySQL or PostgreSQL database. Some also allow exporting a diagram image or sharing a read-only link to the schema.</p>
            </div>
            <div class="faq-item">
                <h3>Can I use a database designer tool without installing anything?</h3>
                <p>Yes. Browser-based database designer tools run entirely in your browser. There's nothing to download or install. Create a free account and start designing immediately from any device, including a laptop, desktop, or tablet.</p>
            </div>
            <div class="faq-item">
                <h3>What is the difference between a database designer and a generic diagram tool?</h3>
                <p>A generic diagram tool (like draw.io or Figma) lets you draw boxes and lines but doesn't understand SQL. A purpose-built database designer knows your column types, validates constraints, and generates a correct <code>CREATE TABLE</code> script. The diagram and the DDL stay in sync — something a generic tool can't do.</p>
            </div>
            <div class="faq-item">
                <h3>Do free database designer tools have limits on diagrams or tables?</h3>
                <p>Some tools limit free accounts to a small number of diagrams or tables per diagram. Others, like SQL Designer, are fully free with no diagram count limits, no table limits, and no SQL export paywall. Always check the pricing page before committing to a free tier.</p>
            </div>
        </section>

        <nav aria-label="Related articles" style="margin-top:3rem; padding-top:2rem; border-top:1px solid var(--border-color);">
            <p style="font-size:0.875rem; text-transform:uppercase; letter-spacing:0.06em; color:#767676; margin:0 0 0.8rem;">Related Articles</p>
            <ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:0.5rem;">
                <li><a href="/blog/database-schema-examples" style="color:var(--color-primary); font-size:0.88rem; text-decoration:none;">Database Schema Examples &rarr;</a></li>
                <li><a href="/blog/crowfoot-notation" style="color:var(--color-primary); font-size:0.88rem; text-decoration:none;">Crow's Foot Notation Explained &rarr;</a></li>
            </ul>
        </nav>

        <div class="cta-box">
            <h3>Start designing your database for free</h3>
            <p>SQL Designer is a free online database designer for MySQL and PostgreSQL. No install, no subscription — design visually and export SQL in minutes.</p>
            <a class="btn-cta" href="/register">Create a Free Account</a>
        </div>
    </article>
@endsection

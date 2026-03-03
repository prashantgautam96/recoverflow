<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RecoverFlow | Laravel Invoice Recovery SaaS</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|fraunces:500,700" rel="stylesheet" />
        <style>
            :root {
                --bg: #f6f1e8;
                --ink: #112a26;
                --accent: #e85d2a;
                --muted: #4b5d59;
                --panel: #fff9f0;
                --shadow: 0 30px 60px rgba(17, 42, 38, 0.14);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: 'Space Grotesk', sans-serif;
                background: radial-gradient(circle at 10% 10%, #ffedd5 0%, var(--bg) 40%), linear-gradient(120deg, #f6f1e8 0%, #ecf6f2 100%);
                color: var(--ink);
                min-height: 100vh;
            }

            .wrap {
                max-width: 1080px;
                margin: 0 auto;
                padding: 2rem 1.2rem 4rem;
            }

            .hero {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
                align-items: stretch;
            }

            .title {
                font-family: 'Fraunces', serif;
                font-size: clamp(2rem, 5vw, 4rem);
                line-height: 0.98;
                margin: 0;
                letter-spacing: -0.02em;
            }

            .subtitle {
                color: var(--muted);
                font-size: 1.08rem;
                line-height: 1.6;
                max-width: 46ch;
            }

            .badge {
                display: inline-flex;
                border: 1px solid rgba(17, 42, 38, 0.16);
                border-radius: 999px;
                padding: 0.4rem 0.9rem;
                font-size: 0.8rem;
                margin-bottom: 1rem;
                background: rgba(255, 255, 255, 0.7);
            }

            .panel {
                background: var(--panel);
                border-radius: 18px;
                border: 1px solid rgba(17, 42, 38, 0.1);
                padding: 1.2rem;
                box-shadow: var(--shadow);
            }

            .card-grid {
                margin-top: 1.5rem;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 1rem;
            }

            .card h3 {
                margin: 0 0 0.45rem;
                font-size: 1.1rem;
            }

            .card p {
                margin: 0;
                color: var(--muted);
                line-height: 1.55;
                font-size: 0.95rem;
            }

            .pricing {
                margin-top: 1.5rem;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 1rem;
            }

            .price {
                padding: 1rem;
                border-radius: 14px;
                background: #fff;
                border: 1px solid rgba(17, 42, 38, 0.12);
            }

            .price strong {
                display: block;
                font-size: 1.7rem;
                margin-top: 0.4rem;
                color: var(--accent);
            }

            pre {
                margin: 0;
                white-space: pre-wrap;
                font-size: 0.85rem;
                color: #1f3833;
                background: #fff;
                border: 1px solid rgba(17, 42, 38, 0.1);
                border-radius: 12px;
                padding: 0.85rem;
                line-height: 1.55;
                overflow-x: auto;
            }

            .highlight {
                color: var(--accent);
            }

            @media (max-width: 920px) {
                .hero,
                .card-grid,
                .pricing {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <main class="wrap">
            <section class="hero">
                <article class="panel">
                    <span class="badge">Laravel 12 Micro SaaS</span>
                    <h1 class="title">RecoverFlow turns overdue invoices into cash.</h1>
                    <p class="subtitle">
                        API-key based invoice recovery for agencies and freelancers. Create clients, create invoices, auto-schedule
                        reminders, mark payments, and track recovery metrics from one deployable Laravel app.
                    </p>
                    <p class="subtitle">
                        <span class="highlight">Revenue model:</span> monthly subscriptions plus optional success fee on recovered
                        overdue invoices.
                    </p>
                    <p style="margin-top:1rem;">
                        <a
                            href="/app"
                            style="display:inline-block;padding:0.65rem 1rem;border-radius:12px;background:#112a26;color:#fff;text-decoration:none;font-weight:600;"
                        >
                            Start Your Beta Trial
                        </a>
                    </p>
                </article>

                <article class="panel">
                    <h2>Why Teams Use RecoverFlow</h2>
                    <p class="subtitle" style="margin-top:0;">
                        Stop chasing every overdue invoice manually. RecoverFlow gives you one place to track open invoices,
                        automate follow-ups, and move more payments to completed.
                    </p>
                    <p class="subtitle" style="margin-top:0.8rem;">
                        Built for freelancers and agencies who want better cash flow without adding extra admin work.
                    </p>
                    <p style="margin-top:1rem;">
                        <a
                            href="/app"
                            style="display:inline-block;padding:0.65rem 1rem;border-radius:12px;background:#e85d2a;color:#fff;text-decoration:none;font-weight:600;"
                        >
                            Join Beta
                        </a>
                    </p>
                </article>
            </section>

            <section class="card-grid">
                <article class="card panel">
                    <h3>API Key Billing Boundary</h3>
                    <p>Each tenant key has its own quota, usage tracking, clients, invoices, and reminders.</p>
                </article>
                <article class="card panel">
                    <h3>Automated Recovery Cadence</h3>
                    <p>Invoice creation auto-schedules reminder waves for due day + 3, 7, and 14 days.</p>
                </article>
                <article class="card panel">
                    <h3>Recovery Dashboard</h3>
                    <p>Track outstanding value, overdue totals, paid invoices, reminders due now, and API usage.</p>
                </article>
            </section>

            <section class="pricing">
                <article class="price">
                    <div>Solo</div>
                    <strong>$9/mo (Beta)</strong>
                    <div>Up to 5k API calls</div>
                </article>
                <article class="price">
                    <div>Team</div>
                    <strong>$19/mo (Beta)</strong>
                    <div>Up to 25k API calls</div>
                </article>
                <article class="price">
                    <div>Scale</div>
                    <strong>$49/mo (Beta)</strong>
                    <div>Up to 100k API calls</div>
                </article>
            </section>

            <section class="panel" style="margin-top:1.5rem;">
                <h2>How It Works</h2>
                <p class="subtitle" style="margin-top:0;">
                    1. Add your client and invoice details.
                </p>
                <p class="subtitle" style="margin-top:0;">
                    2. RecoverFlow schedules and sends reminder emails automatically.
                </p>
                <p class="subtitle" style="margin-top:0;">
                    3. Track overdue and recovered amounts in one dashboard.
                </p>
            </section>
        </main>
    </body>
</html>

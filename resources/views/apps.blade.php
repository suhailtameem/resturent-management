<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1f3d1b">
    <meta name="description" content="حمّل تطبيقات أرواق العنب الرسمية للعملاء ومديري المطاعم.">
    <title>تطبيقات أرواق العنب الرسمية</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --vine: #1f3d1b;
            --vine-dark: #142b12;
            --gold: #c9a227;
            --gold-soft: #f2dfaa;
            --cream: #fff8ef;
            --cream-deep: #f4ead8;
            --chocolate: #34180f;
            --muted: #70675b;
            --white: #fff;
            --line: rgba(52, 24, 15, .1);
            font-family: 'Cairo', sans-serif;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            min-width: 320px;
            background: var(--cream);
            color: var(--chocolate);
            font-family: 'Cairo', sans-serif;
            line-height: 1.7;
        }
        a { color: inherit; }
        .shell { width: min(100% - 32px, 1040px); margin-inline: auto; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 1px solid rgba(52, 24, 15, .07);
            background: rgba(255, 248, 239, .92);
            backdrop-filter: blur(16px);
        }
        .header-inner { min-height: 70px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: flex; align-items: center; gap: 11px; min-width: 0; text-decoration: none; }
        .brand img { width: 46px; height: 46px; object-fit: cover; border: 1px solid rgba(201, 162, 39, .4); border-radius: 50%; background: var(--white); }
        .brand-name { display: block; color: var(--chocolate); font-size: 16px; font-weight: 900; line-height: 1.25; }
        .brand-caption { display: block; color: var(--muted); font-size: 11px; font-weight: 600; }
        .header-link, .download-link {
            display: inline-flex;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border-radius: 999px;
            padding: 9px 18px;
            background: var(--vine);
            color: var(--white);
            font-weight: 800;
            text-decoration: none;
            transition: transform .18s ease, background .18s ease, box-shadow .18s ease;
        }
        .header-link { flex: 0 0 auto; min-height: 42px; padding-inline: 15px; font-size: 13px; }
        .header-link:hover, .download-link:hover { transform: translateY(-2px); background: var(--vine-dark); box-shadow: 0 8px 20px rgba(31, 61, 27, .18); }
        .hero { padding-block: 28px 22px; }
        .hero-panel {
            position: relative;
            overflow: hidden;
            padding: 32px 20px;
            border: 1px solid rgba(201, 162, 39, .2);
            border-radius: 28px;
            background: radial-gradient(circle at 15% 10%, rgba(201, 162, 39, .16), transparent 32%), linear-gradient(145deg, #fffdf8, #f4ead8);
            box-shadow: 0 18px 50px rgba(52, 24, 15, .07);
            text-align: center;
        }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: var(--chocolate); color: var(--gold-soft); font-size: 12px; font-weight: 800; }
        .eyebrow-mark { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); box-shadow: 0 0 0 4px rgba(201, 162, 39, .18); }
        h1 { max-width: 700px; margin: 17px auto 10px; color: var(--chocolate); font-size: clamp(27px, 7vw, 42px); font-weight: 900; line-height: 1.35; }
        .hero-copy { max-width: 620px; margin: 0 auto; color: #5e554b; font-size: 15px; }
        .hero-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 22px; }
        .hero-actions a { min-height: 46px; padding-inline: 20px; }
        .hero-actions .secondary { background: var(--chocolate); }
        .proofs { display: flex; flex-wrap: wrap; justify-content: center; gap: 9px 20px; margin-top: 20px; color: #62594d; font-size: 12px; font-weight: 700; }
        .proof { display: inline-flex; align-items: center; gap: 7px; }
        .proof-dot { display: inline-grid; width: 19px; height: 19px; place-items: center; border-radius: 50%; background: rgba(31, 61, 27, .1); color: var(--vine); font-size: 12px; }
        .section-heading { margin: 22px 0 14px; text-align: center; }
        .section-heading h2 { margin: 0; font-size: 23px; font-weight: 900; }
        .section-heading p { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .app-grid { display: grid; gap: 16px; padding-bottom: 28px; }
        .app-card { overflow: hidden; border: 1px solid var(--line); border-radius: 22px; background: var(--white); box-shadow: 0 12px 32px rgba(52, 24, 15, .06); }
        .card-accent { height: 6px; background: linear-gradient(90deg, var(--vine), #6e8b2e, var(--gold)); }
        .card-content { padding: 20px; }
        .app-heading { display: flex; align-items: flex-start; gap: 14px; }
        .app-logo { display: grid; width: 64px; height: 64px; flex: 0 0 64px; place-items: center; overflow: hidden; border: 1px solid rgba(201, 162, 39, .26); border-radius: 18px; background: var(--cream); }
        .app-logo img { width: 100%; height: 100%; object-fit: cover; }
        .app-title { min-width: 0; }
        .app-title h3 { margin: 0; font-size: 20px; font-weight: 900; line-height: 1.4; }
        .app-subtitle { margin: 4px 0 0; color: var(--muted); font-size: 12px; font-weight: 600; }
        .platform { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; padding: 3px 10px; border-radius: 999px; background: #eff4eb; color: var(--vine); font-size: 11px; font-weight: 800; }
        .app-description { margin: 16px 0 12px; color: #554d44; font-size: 13px; }
        .feature-list { display: grid; gap: 8px; margin: 0 0 18px; padding: 0; list-style: none; }
        .feature-list li { display: flex; align-items: flex-start; gap: 9px; color: #4e473f; font-size: 12px; }
        .check { color: var(--gold); font-size: 16px; font-weight: 900; line-height: 1.4; }
        .download-link { width: 100%; min-height: 50px; }
        .download-link.manager { background: var(--chocolate); }
        .download-link.unavailable { cursor: not-allowed; background: #e9e3d9; color: #6a6259; box-shadow: none; }
        .download-note { margin: 9px 0 0; color: var(--muted); font-size: 11px; text-align: center; }
        .install { margin-bottom: 24px; padding: 20px; border: 1px solid rgba(201, 162, 39, .18); border-radius: 22px; background: #f8efdf; }
        .install h2 { margin: 0; font-size: 19px; font-weight: 900; }
        .install-intro { margin: 4px 0 14px; color: var(--muted); font-size: 12px; }
        .steps { display: grid; gap: 10px; }
        .step { display: flex; align-items: flex-start; gap: 11px; padding: 12px; border: 1px solid rgba(52, 24, 15, .07); border-radius: 15px; background: rgba(255, 255, 255, .8); }
        .step-number { display: grid; width: 28px; height: 28px; flex: 0 0 28px; place-items: center; border-radius: 50%; background: var(--vine); color: var(--white); font-size: 13px; font-weight: 900; }
        .step p { margin: 1px 0 0; color: #554d44; font-size: 12px; }
        .notice { margin: 0 0 24px; padding: 13px 15px; border-inline-start: 4px solid var(--gold); border-radius: 12px; background: #fffdf7; color: #5e554b; font-size: 12px; }
        .site-footer { padding: 22px 16px 28px; background: var(--chocolate); color: #f7edda; text-align: center; }
        .footer-logo { width: 42px; height: 42px; object-fit: cover; border: 1px solid rgba(242, 223, 170, .45); border-radius: 50%; }
        .site-footer p { margin: 5px 0 0; color: #d4c6ae; font-size: 11px; }
        @media (min-width: 720px) {
            .shell { width: min(100% - 48px, 1040px); }
            .hero { padding-block: 42px 28px; }
            .hero-panel { padding: 46px 36px; }
            .app-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; padding-bottom: 36px; }
            .card-content { padding: 24px; }
            .steps { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .install { padding: 24px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="#top" aria-label="أرواق العنب - الرئيسية">
                <img src="{{ asset('images/apps/customer-logo.jpeg') }}" alt="شعار أرواق العنب">
                <span>
                    <span class="brand-name">أرواق العنب</span>
                    <span class="brand-caption">التطبيقات الرسمية</span>
                </span>
            </a>
            <a class="header-link" href="#download-cards"><span aria-hidden="true">↓</span> تحميل التطبيقات</a>
        </div>
    </header>

    <main id="top">
        <section class="shell hero" aria-labelledby="page-title">
            <div class="hero-panel">
                <span class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> المنظومة الرقمية الرسمية</span>
                <h1 id="page-title">تطبيقات أرواق العنب، أقرب إليك</h1>
                <p class="hero-copy">اختر التطبيق المناسب لك: تجربة طلب سهلة لعملائنا، وأدوات واضحة لإدارة المطعم ومتابعة الطلبات لفريقنا.</p>
                <div class="hero-actions">
                    <a class="download-link" href="#customer-app"><span aria-hidden="true">◉</span> تطبيق العملاء</a>
                    <a class="download-link secondary" href="#manager-app"><span aria-hidden="true">▣</span> تطبيق إدارة المطعم</a>
                </div>
                <div class="proofs" aria-label="معلومات التطبيقات">
                    <span class="proof"><span class="proof-dot" aria-hidden="true">✓</span> روابط التنزيل الرسمية</span>
                    <span class="proof"><span class="proof-dot" aria-hidden="true">✓</span> واجهة عربية سهلة</span>
                    <span class="proof"><span class="proof-dot" aria-hidden="true">✓</span> تجربة مناسبة للجوال</span>
                </div>
            </div>
        </section>

        <section class="shell" id="download-cards" aria-labelledby="apps-heading">
            <div class="section-heading">
                <h2 id="apps-heading">اختر تطبيقك</h2>
                <p>حمّل التطبيق المناسب وابدأ الاستخدام على جهازك.</p>
            </div>

            <div class="app-grid">
                <article class="app-card" id="customer-app">
                    <div class="card-accent"></div>
                    <div class="card-content">
                        <div class="app-heading">
                            <div class="app-logo"><img src="{{ asset('images/apps/customer-logo.jpeg') }}" alt="" loading="lazy"></div>
                            <div class="app-title">
                                <h3>تطبيق العملاء</h3>
                                <p class="app-subtitle">اطلب وجبتك وتابع طلبك بسهولة</p>
                                <span class="platform"><span aria-hidden="true">●</span> أندرويد</span>
                            </div>
                        </div>
                        <p class="app-description">تصفح قائمة الطعام، خصص طلبك، وتابع حالته خطوة بخطوة من هاتفك.</p>
                        <ul class="feature-list">
                            <li><span class="check" aria-hidden="true">✓</span><span>استعراض الأصناف والأحجام والإضافات.</span></li>
                            <li><span class="check" aria-hidden="true">✓</span><span>إتمام الطلب ومتابعة تحديثاته.</span></li>
                            <li><span class="check" aria-hidden="true">✓</span><span>حفظ عناوين التوصيل ومراجعة الطلبات.</span></li>
                        </ul>
                        @if ($customerAppUrl)
                            <a class="download-link" href="{{ $customerAppUrl }}" download>
                                <span aria-hidden="true">↓</span> تحميل تطبيق العملاء
                            </a>
                        @else
                            <span class="download-link unavailable" aria-disabled="true">رابط التنزيل قيد الإعداد</span>
                            <p class="download-note">سيظهر زر التحميل بعد إضافة رابط التطبيق إلى إعدادات الموقع.</p>
                        @endif
                    </div>
                </article>

                <article class="app-card" id="manager-app">
                    <div class="card-accent"></div>
                    <div class="card-content">
                        <div class="app-heading">
                            <div class="app-logo"><img src="{{ asset('images/apps/manager-logo.png') }}" alt="" loading="lazy"></div>
                            <div class="app-title">
                                <h3>تطبيق إدارة المطعم</h3>
                                <p class="app-subtitle">للمديرين وطاقم العمل المخوّل</p>
                                <span class="platform"><span aria-hidden="true">●</span> أندرويد</span>
                            </div>
                        </div>
                        <p class="app-description">تابع الطلبات وحالاتها، ونسّق العمل اليومي من تطبيق واحد مخصص للفريق.</p>
                        <ul class="feature-list">
                            <li><span class="check" aria-hidden="true">✓</span><span>متابعة الطلبات وتحديث مراحلها.</span></li>
                            <li><span class="check" aria-hidden="true">✓</span><span>إدارة الطلبات وتعيين مندوبي التوصيل.</span></li>
                            <li><span class="check" aria-hidden="true">✓</span><span>إرسال تنبيهات للعملاء عند تحديث الطلب.</span></li>
                        </ul>
                        @if ($managerAppUrl)
                            <a class="download-link manager" href="{{ $managerAppUrl }}" download>
                                <span aria-hidden="true">↓</span> تحميل تطبيق الإدارة
                            </a>
                        @else
                            <span class="download-link manager unavailable" aria-disabled="true">رابط التنزيل قيد الإعداد</span>
                            <p class="download-note">سيظهر زر التحميل بعد إضافة رابط التطبيق إلى إعدادات الموقع.</p>
                        @endif
                    </div>
                </article>
            </div>
        </section>

        <section class="shell install" aria-labelledby="install-heading">
            <h2 id="install-heading">طريقة تثبيت التطبيق</h2>
            <p class="install-intro">بعد تنزيل ملف APK على جهاز أندرويد:</p>
            <div class="steps">
                <div class="step"><span class="step-number">١</span><p>افتح ملف التطبيق الذي تم تنزيله.</p></div>
                <div class="step"><span class="step-number">٢</span><p>اسمح بالتثبيت من هذا المصدر إذا طلب أندرويد ذلك.</p></div>
                <div class="step"><span class="step-number">٣</span><p>اضغط «تثبيت»، ثم افتح التطبيق.</p></div>
            </div>
        </section>

        <aside class="shell notice" role="note">للحصول على آخر نسخة، استخدم روابط التنزيل الرسمية المنشورة على هذه الصفحة.</aside>
    </main>

    <footer class="site-footer">
        <img class="footer-logo" src="{{ asset('images/apps/customer-logo.jpeg') }}" alt="شعار أرواق العنب" loading="lazy">
        <p><strong>أرواق العنب</strong> · التطبيقات الرسمية</p>
        <p>© {{ date('Y') }} أرواق العنب. جميع الحقوق محفوظة.</p>
    </footer>
</body>
</html>

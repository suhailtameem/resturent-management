<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f3d1b">
    <meta name="description" content="Learn how Arwaq Al-Eneab collects, uses, and protects your personal data.">
    <title>Privacy Policy | {{ $storeNameEn }}</title>
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
            min-width: 320px;
            margin: 0;
            background: var(--cream);
            color: var(--chocolate);
            font-family: 'Cairo', sans-serif;
            line-height: 1.75;
        }
        a { color: inherit; }
        .shell { width: min(100% - 32px, 980px); margin-inline: auto; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 2;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 248, 239, .94);
            backdrop-filter: blur(14px);
        }
        .header-inner {
            display: flex;
            min-height: 72px;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand { display: flex; min-width: 0; align-items: center; gap: 11px; text-decoration: none; }
        .brand img {
            width: 46px;
            height: 46px;
            flex: 0 0 46px;
            border: 1px solid rgba(201, 162, 39, .4);
            border-radius: 50%;
            background: var(--white);
            object-fit: cover;
        }
        .brand-name { display: block; font-size: 15px; font-weight: 800; line-height: 1.35; }
        .brand-caption { display: block; color: var(--muted); font-size: 11px; }
        .language-control { display: flex; align-items: center; gap: 8px; }
        .language-label { color: var(--muted); font-size: 12px; font-weight: 700; }
        .language-select {
            min-height: 42px;
            border: 1px solid rgba(52, 24, 15, .16);
            border-radius: 999px;
            padding: 8px 14px;
            background: var(--white);
            color: var(--chocolate);
            font: inherit;
            font-size: 13px;
            font-weight: 700;
        }
        .language-select:focus-visible, a:focus-visible {
            outline: 3px solid rgba(201, 162, 39, .7);
            outline-offset: 3px;
        }
        .hero { padding-block: 30px 20px; }
        .hero-panel {
            padding: clamp(24px, 5vw, 42px);
            border: 1px solid rgba(201, 162, 39, .25);
            border-radius: 26px;
            background:
                radial-gradient(circle at 12% 10%, rgba(201, 162, 39, .2), transparent 34%),
                linear-gradient(145deg, #fffdf8, var(--cream-deep));
            box-shadow: 0 16px 40px rgba(52, 24, 15, .07);
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 5px 12px;
            background: var(--chocolate);
            color: var(--gold-soft);
            font-size: 12px;
            font-weight: 700;
        }
        .eyebrow-mark { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); }
        h1 { margin: 14px 0 5px; font-size: clamp(30px, 6vw, 44px); line-height: 1.2; }
        .hero-copy { max-width: 650px; margin: 10px 0 0; color: #5e554b; }
        .updated { margin: 17px 0 0; color: var(--muted); font-size: 13px; font-weight: 700; }
        main { padding-bottom: 34px; }
        .policy-card {
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: clamp(18px, 4vw, 38px);
            background: var(--white);
            box-shadow: 0 12px 32px rgba(52, 24, 15, .05);
        }
        .policy-section { padding-block: 20px; border-bottom: 1px solid var(--line); }
        .policy-section:first-child { padding-top: 0; }
        .policy-section:last-child { padding-bottom: 0; border-bottom: 0; }
        .policy-section h2 { margin: 0 0 9px; color: var(--vine); font-size: 20px; line-height: 1.4; }
        .policy-section p { margin: 8px 0; color: #554d44; }
        .policy-section ul { display: grid; gap: 7px; margin: 10px 0; padding-inline-start: 22px; color: #554d44; }
        .policy-section li::marker { color: var(--gold); }
        .policy-note {
            margin-top: 12px;
            border-inline-start: 4px solid var(--gold);
            border-radius: 10px;
            padding: 11px 14px;
            background: #fff9e9;
            color: #554d44;
        }
        .site-footer { padding: 24px 16px 30px; background: var(--chocolate); color: #f7edda; text-align: center; }
        .site-footer p { margin: 5px 0; }
        .footer-logo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .footer-link { color: var(--gold-soft); }
        [hidden] { display: none !important; }

        @media (max-width: 520px) {
            .shell { width: min(100% - 22px, 980px); }
            .header-inner { min-height: 64px; gap: 8px; }
            .brand img { width: 40px; height: 40px; flex-basis: 40px; }
            .brand-name { font-size: 13px; }
            .brand-caption { font-size: 10px; }
            .language-control { gap: 5px; }
            .language-label { display: none; }
            .language-select { min-height: 40px; padding-inline: 10px; }
            .hero { padding-top: 18px; }
            .hero-panel { border-radius: 20px; }
            .policy-card { border-radius: 18px; }
        }
    </style>
</head>
<body data-title-en="Privacy Policy | {{ $storeNameEn }}" data-title-ar="سياسة الخصوصية | {{ $storeNameAr }}">
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="{{ url('/') }}">
                <img src="{{ asset('images/main-logo.png') }}" alt="">
                <span>
                    <strong class="brand-name" data-language="en">{{ $storeNameEn }}</strong>
                    <strong class="brand-name" data-language="ar" hidden>{{ $storeNameAr }}</strong>
                    <span class="brand-caption" data-language="en">Customer privacy</span>
                    <span class="brand-caption" data-language="ar" hidden>خصوصية العملاء</span>
                </span>
            </a>
            <div class="language-control">
                <label class="language-label" for="language-selector">
                    <span data-language="en">Language</span>
                    <span data-language="ar" hidden>اللغة</span>
                </label>
                <select class="language-select" id="language-selector" aria-label="Language">
                    <option value="en" selected>English</option>
                    <option value="ar">العربية</option>
                </select>
            </div>
        </div>
    </header>

    <main class="shell" id="top">
        <section class="hero" aria-labelledby="page-title">
            <div class="hero-panel">
                <span class="eyebrow">
                    <span class="eyebrow-mark" aria-hidden="true"></span>
                    <span data-language="en">{{ $storeNameEn }}</span>
                    <span data-language="ar" hidden>{{ $storeNameAr }}</span>
                </span>
                <h1 id="page-title">
                    <span data-language="en">Privacy Policy</span>
                    <span data-language="ar" hidden>سياسة الخصوصية</span>
                </h1>
                <p class="hero-copy" data-language="en">
                    This policy explains how we handle personal data when you use our customer app and ordering services.
                </p>
                <p class="hero-copy" data-language="ar" hidden>
                    توضح هذه السياسة كيفية تعاملنا مع بياناتك الشخصية عند استخدام تطبيق العملاء وخدمات الطلب.
                </p>
                <p class="updated">
                    <span data-language="en">Last updated: November 7, 2026</span>
                    <span data-language="ar" hidden>آخر تحديث: 7 نوفمبر 2026</span>
                </p>
            </div>
        </section>

        <section class="policy-card" id="policy-en" data-policy-language="en" aria-label="Privacy policy in English">
            <article class="policy-section">
                <h2>1. Introduction</h2>
                <p>
                    This policy applies to the {{ $storeNameEn }} customer app and its ordering and delivery services
                    (the “App” and “Services”). We operate in Saudi Arabia and handle personal data in accordance
                    with applicable laws, including the Saudi Personal Data Protection Law (PDPL).
                </p>
                <p>
                    It describes the information we collect, how we use and share it, how long we keep it, and the
                    choices and rights available to you.
                </p>
            </article>

            <article class="policy-section">
                <h2>2. Personal Data We Collect</h2>
                <p>Depending on how you use the Services, we may process:</p>
                <ul>
                    <li><strong>Account and contact details:</strong> your name, mobile number, optional email address, preferred language, and phone-verification status.</li>
                    <li><strong>Saved addresses:</strong> address labels, city, district, street details, delivery notes, and location coordinates when you provide them.</li>
                    <li><strong>Orders:</strong> products, sizes, quantities, options, special instructions, delivery or pickup details, order status and history, coupons or offers used, and order totals.</li>
                    <li><strong>Payment records:</strong> the selected payment method and status, amount and currency, and provider or transaction references for online payments. Our application database does not store full payment-card numbers.</li>
                    <li><strong>App notifications:</strong> your device platform and push-notification token, along with notifications sent to you and whether you opened them.</li>
                    <li><strong>Support requests:</strong> information you choose to share when you contact us.</li>
                </ul>
            </article>

            <article class="policy-section">
                <h2>3. How We Use Personal Data</h2>
                <ul>
                    <li>To create and maintain your account and verify your mobile number.</li>
                    <li>To save delivery addresses, prepare and fulfill orders, and provide order tracking and status updates.</li>
                    <li>To calculate order totals and apply eligible discounts, coupons, and offers.</li>
                    <li>To process or record the payment method and payment status selected for an order.</li>
                    <li>To send service notifications and, where applicable, information about offers.</li>
                    <li>To provide customer support, protect the Services, prevent misuse, and meet legal obligations.</li>
                </ul>
            </article>

            <article class="policy-section">
                <h2>4. How We Share Personal Data</h2>
                <p>We do not sell or rent your personal data. We share only what is needed to provide the Services:</p>
                <ul>
                    <li><strong>Delivery staff:</strong> the order, contact, and address details needed to complete delivery.</li>
                    <li><strong>Service providers:</strong> providers supporting push notifications (including Firebase Cloud Messaging), hosting or app operations, and online-payment processing when that method is used.</li>
                    <li><strong>Authorities:</strong> when required by applicable law or a valid legal request.</li>
                </ul>
                <p>Service providers may process information under their own privacy terms and our instructions or applicable agreements.</p>
            </article>

            <article class="policy-section">
                <h2>5. Location and Notifications</h2>
                <p>
                    Location coordinates can be stored with a saved address when you provide them to help fulfill a
                    delivery. You can provide an address without coordinates where the App allows it.
                </p>
                <p>
                    The App uses a device notification token to send order updates and service messages. You can
                    manage notification permissions in your device settings; removing a token from the App stops
                    notifications to that token.
                </p>
            </article>

            <article class="policy-section">
                <h2>6. Tracking and Advertising</h2>
                <p>
                    Our current backend does not implement cross-app advertising tracking or third-party advertising
                    profiles. We may send service notifications and information about our own offers. Your device
                    platform or a third-party payment provider may process data under its own policies.
                </p>
            </article>

            <article class="policy-section">
                <h2>7. Data Retention</h2>
                <p>
                    We keep account, address, notification, and order information for as long as needed to operate the
                    Services, provide support, and meet legal or accounting obligations.
                </p>
                <p>
                    When an account is deleted, we remove the account and its personal profile data. Order records
                    that must be retained may remain in an anonymized form, and account deletion may be delayed while
                    an order is active.
                </p>
            </article>

            <article class="policy-section">
                <h2>8. Data Security</h2>
                <p>
                    We use safeguards intended to protect personal data, including authenticated access, role-based
                    access controls, and protected account credentials and session tokens. No method of transmission
                    or storage is completely secure.
                </p>
            </article>

            <article class="policy-section">
                <h2>9. Your Rights and Account Deletion</h2>
                <p>Subject to applicable law, you may request access to, a copy of, correction of, or deletion of your personal data, and exercise other rights available to you.</p>
                <p>
                    You can request account deletion through the customer App or by contacting us using the support
                    details below. Deletion is available after active orders are completed. We remove your account
                    and saved addresses and anonymize order records that must be retained.
                </p>
            </article>

            <article class="policy-section">
                <h2>10. Children</h2>
                <p>
                    The Services are not directed to children under 13, and we do not knowingly collect their
                    personal data. If you believe a child has provided us with personal data, contact us so we can
                    review and address the request.
                </p>
            </article>

            <article class="policy-section">
                <h2>11. Changes to This Policy</h2>
                <p>
                    We may update this policy as our Services or legal obligations change. We will publish the
                    updated policy here and revise the “Last updated” date. Where required, we will notify you of
                    material changes.
                </p>
            </article>

            <article class="policy-section">
                <h2>12. Contact Us</h2>
                <p>For privacy questions or requests, contact {{ $storeNameEn }} at <a href="tel:{{ $supportPhone }}">{{ $supportPhone }}</a>.</p>
            </article>
        </section>

        <section class="policy-card" id="policy-ar" data-policy-language="ar" aria-label="سياسة الخصوصية باللغة العربية" dir="rtl" hidden>
            <article class="policy-section">
                <h2>١. مقدمة</h2>
                <p>
                    تنطبق هذه السياسة على تطبيق عملاء {{ $storeNameAr }} وخدمات الطلب والتوصيل المرتبطة به
                    («التطبيق» و«الخدمات»). نقدم خدماتنا في المملكة العربية السعودية ونتعامل مع البيانات الشخصية
                    وفق الأنظمة المعمول بها، بما فيها نظام حماية البيانات الشخصية السعودي.
                </p>
                <p>توضح السياسة البيانات التي نجمعها، وكيفية استخدامها ومشاركتها والاحتفاظ بها، والخيارات والحقوق المتاحة لك.</p>
            </article>

            <article class="policy-section">
                <h2>٢. البيانات الشخصية التي نجمعها</h2>
                <p>بحسب طريقة استخدامك للخدمات، قد نعالج البيانات التالية:</p>
                <ul>
                    <li><strong>بيانات الحساب والتواصل:</strong> الاسم ورقم الجوال والبريد الإلكتروني الاختياري واللغة المفضلة وحالة التحقق من رقم الجوال.</li>
                    <li><strong>العناوين المحفوظة:</strong> اسم العنوان والمدينة والحي وتفاصيل الشارع وملاحظات التوصيل والإحداثيات عند تزويدنا بها.</li>
                    <li><strong>الطلبات:</strong> المنتجات والأحجام والكميات والإضافات والتعليمات الخاصة وبيانات التوصيل أو الاستلام وحالة الطلب وسجله والكوبونات أو العروض المستخدمة وإجمالي الطلب.</li>
                    <li><strong>سجلات الدفع:</strong> طريقة الدفع وحالتها والمبلغ والعملة، وبيانات مرجع مزوّد الدفع أو العملية عند الدفع الإلكتروني. لا تخزّن قاعدة بيانات التطبيق أرقام البطاقات المصرفية كاملة.</li>
                    <li><strong>إشعارات التطبيق:</strong> منصة جهازك ورمز الإشعارات، والإشعارات المرسلة إليك وما إذا كنت قد فتحتها.</li>
                    <li><strong>طلبات الدعم:</strong> المعلومات التي تختار مشاركتها عند التواصل معنا.</li>
                </ul>
            </article>

            <article class="policy-section">
                <h2>٣. كيف نستخدم البيانات الشخصية</h2>
                <ul>
                    <li>إنشاء حسابك وإدارته والتحقق من رقم جوالك.</li>
                    <li>حفظ عناوين التوصيل وتجهيز الطلبات وتنفيذها وإتاحة متابعة حالتها.</li>
                    <li>احتساب إجمالي الطلب وتطبيق الخصومات والكوبونات والعروض المستحقة.</li>
                    <li>معالجة أو تسجيل طريقة الدفع وحالة الدفع المحددة للطلب.</li>
                    <li>إرسال إشعارات الخدمة ومعلومات العروض عند الاقتضاء.</li>
                    <li>تقديم الدعم وحماية الخدمات ومنع إساءة الاستخدام والوفاء بالالتزامات النظامية.</li>
                </ul>
            </article>

            <article class="policy-section">
                <h2>٤. مشاركة البيانات الشخصية</h2>
                <p>لا نبيع بياناتك الشخصية ولا نؤجرها. ونشارك فقط ما يلزم لتقديم الخدمات مع:</p>
                <ul>
                    <li><strong>مندوبو التوصيل:</strong> بيانات الطلب والتواصل والعنوان اللازمة لإتمام التوصيل.</li>
                    <li><strong>مزوّدو الخدمات:</strong> الجهات التي تدعم الإشعارات (بما فيها Firebase Cloud Messaging) أو الاستضافة وتشغيل التطبيق أو معالجة الدفع الإلكتروني عند استخدامه.</li>
                    <li><strong>الجهات الرسمية:</strong> عندما يتطلب النظام ذلك أو بموجب طلب نظامي صحيح.</li>
                </ul>
                <p>قد يعالج مزوّدو الخدمات البيانات وفق سياسات الخصوصية الخاصة بهم وتعليماتنا أو الاتفاقيات المعمول بها.</p>
            </article>

            <article class="policy-section">
                <h2>٥. الموقع الجغرافي والإشعارات</h2>
                <p>قد تُحفظ إحداثيات الموقع مع العنوان عند تزويدنا بها للمساعدة في التوصيل. ويمكنك إدخال العنوان دون إحداثيات إذا أتاح التطبيق ذلك.</p>
                <p>يستخدم التطبيق رمز إشعارات الجهاز لإرسال تحديثات الطلب ورسائل الخدمة. يمكنك إدارة أذونات الإشعارات من إعدادات جهازك؛ وإزالة الرمز من التطبيق توقف الإشعارات المرسلة إليه.</p>
            </article>

            <article class="policy-section">
                <h2>٦. التتبع والإعلانات</h2>
                <p>
                    لا يطبّق نظامنا الحالي تتبعاً إعلانياً عبر التطبيقات أو ملفات تعريف إعلانية لأطراف خارجية.
                    وقد نرسل إشعارات الخدمة ومعلومات عن عروضنا. وقد تعالج منصة جهازك أو جهة الدفع بيانات وفق سياساتها الخاصة.
                </p>
            </article>

            <article class="policy-section">
                <h2>٧. الاحتفاظ بالبيانات</h2>
                <p>نحتفظ ببيانات الحساب والعناوين والإشعارات والطلبات للمدة اللازمة لتشغيل الخدمات وتقديم الدعم والوفاء بالالتزامات النظامية أو المحاسبية.</p>
                <p>عند حذف الحساب نحذف الحساب وبيانات الملف الشخصي. وقد نحتفظ بسجلات الطلبات التي يلزم الاحتفاظ بها بعد إخفاء هوية صاحبها، وقد يتأخر حذف الحساب إلى حين اكتمال الطلبات النشطة.</p>
            </article>

            <article class="policy-section">
                <h2>٨. أمن البيانات</h2>
                <p>
                    نستخدم تدابير تهدف إلى حماية البيانات، ومنها الدخول الموثّق وضوابط الوصول بحسب الصلاحيات
                    وحماية بيانات الحساب ورموز الجلسات. ومع ذلك لا توجد وسيلة نقل أو تخزين آمنة بالكامل.
                </p>
            </article>

            <article class="policy-section">
                <h2>٩. حقوقك وحذف الحساب</h2>
                <p>وفق الأنظمة المعمول بها، يمكنك طلب الاطلاع على بياناتك أو الحصول على نسخة منها أو تصحيحها أو حذفها وممارسة الحقوق الأخرى المقررة لك.</p>
                <p>
                    يمكنك طلب حذف الحساب من تطبيق العملاء أو بالتواصل معنا عبر بيانات الدعم أدناه. يتاح الحذف بعد
                    اكتمال الطلبات النشطة. نحذف الحساب والعناوين المحفوظة ونخفي هوية صاحب سجلات الطلبات التي يلزم الاحتفاظ بها.
                </p>
            </article>

            <article class="policy-section">
                <h2>١٠. الأطفال</h2>
                <p>الخدمات غير موجهة للأطفال دون سن ١٣ عاماً، ولا نجمع بياناتهم الشخصية عن علم. إذا علمت أن طفلاً زودنا ببيانات شخصية، فتواصل معنا لمراجعة الطلب ومعالجته.</p>
            </article>

            <article class="policy-section">
                <h2>١١. التغييرات على هذه السياسة</h2>
                <p>قد نحدّث هذه السياسة عند تغيير الخدمات أو الالتزامات النظامية. سننشر النسخة المحدّثة هنا ونعدّل تاريخ «آخر تحديث»، وسنبلغك بالتغييرات الجوهرية عند اللزوم.</p>
            </article>

            <article class="policy-section">
                <h2>١٢. تواصل معنا</h2>
                <p>للاستفسارات أو الطلبات المتعلقة بالخصوصية، تواصل مع {{ $storeNameAr }} على <a href="tel:{{ $supportPhone }}" dir="ltr">{{ $supportPhone }}</a>.</p>
            </article>
        </section>
    </main>

    <footer class="site-footer">
        <img class="footer-logo" src="{{ asset('images/main-logo.png') }}" alt="">
        <p><strong data-language="en">{{ $storeNameEn }}</strong><strong data-language="ar" hidden>{{ $storeNameAr }}</strong></p>
        <p data-language="en">© {{ date('Y') }} · Privacy Policy</p>
        <p data-language="ar" hidden>© {{ date('Y') }} · سياسة الخصوصية</p>
        <p><a class="footer-link" href="{{ url('/') }}"><span data-language="en">Home</span><span data-language="ar" hidden>الرئيسية</span></a></p>
    </footer>

    <script>
        const languageSelector = document.querySelector('#language-selector');
        const policySections = document.querySelectorAll('[data-policy-language]');
        const translatedElements = document.querySelectorAll('[data-language]');
        const page = document.body;

        languageSelector.addEventListener('change', () => {
            const language = languageSelector.value;

            document.documentElement.lang = language;
            document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr';
            document.title = page.dataset[`title${language === 'ar' ? 'Ar' : 'En'}`];

            policySections.forEach((section) => {
                section.hidden = section.dataset.policyLanguage !== language;
            });

            translatedElements.forEach((element) => {
                element.hidden = element.dataset.language !== language;
            });

            languageSelector.setAttribute('aria-label', language === 'ar' ? 'اللغة' : 'Language');
        });
    </script>
</body>
</html>

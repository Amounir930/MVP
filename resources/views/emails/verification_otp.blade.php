<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $type === 'register' ? 'رمز تأكيد حسابك الجديد - Conversion Trust' : 'رمز تغيير كلمة المرور - Conversion Trust' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8FAFC;
            color: #334155;
            margin: 0;
            padding: 0;
            direction: rtl;
            text-align: right;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            border: 1px solid #E2E8F0;
            overflow: hidden;
        }
        .header {
            background-color: #4F46E5;
            padding: 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            margin: 0;
            font-weight: 700;
        }
        .content {
            padding: 40px 32px;
        }
        .content p {
            font-size: 16px;
            line-height: 1.6;
            margin-top: 0;
            margin-bottom: 24px;
            color: #475569;
        }
        .otp-container {
            background-color: #F1F5F9;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 32px 0;
            border: 1px dashed #CBD5E1;
        }
        .otp-code {
            font-size: 36px;
            font-weight: 800;
            color: #4F46E5;
            letter-spacing: 8px;
            margin: 0;
        }
        .otp-label {
            font-size: 13px;
            color: #64748B;
            margin-top: 8px;
            font-weight: 600;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #4F46E5;
            color: #ffffff !important;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }
        .footer {
            background-color: #F8FAFC;
            padding: 24px 32px;
            text-align: center;
            border-top: 1px solid #E2E8F0;
        }
        .footer p {
            font-size: 12px;
            color: #94A3B8;
            margin: 0 0 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Conversion Trust</h1>
        </div>
        <div class="content">
            @if ($type === 'register')
                <p>مرحباً بك في Conversion Trust، منصتك لإدارة مراجعات المتاجر والتواصل الذكي.</p>
                <p>لتأكيد بريدك الإلكتروني وإكمال إنشاء حساب التاجر الخاص بك، يرجى الضغط على الرابط أدناه وإدخال رمز التأكيد المرفق عند الطلب:</p>
                
                <div class="otp-container">
                    <div class="otp-code">{{ $code }}</div>
                    <div class="otp-label">رمز تأكيد الحساب (صالح لمدة 60 دقيقة)</div>
                </div>

                @if ($token)
                    <div class="btn-container">
                        <a href="{{ url('/register?email=' . urlencode($email) . '&token=' . $token) }}" class="btn">إكمال تفعيل الحساب</a>
                    </div>
                @endif
            @else
                <p>مرحباً، لقد تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك في Conversion Trust.</p>
                <p>يرجى استخدام الرمز التالي لتأكيد هويتك وتغيير كلمة المرور. إذا لم تكن قد طلبت هذا الإجراء، يمكنك تجاهل هذا البريد الإلكتروني بأمان.</p>

                <div class="otp-container">
                    <div class="otp-code">{{ $code }}</div>
                    <div class="otp-label">رمز تأكيد تغيير كلمة المرور (صالح لمدة 15 دقيقة)</div>
                </div>
            @endif

            <p>نشكرك على ثقتك بنا واستخدامك لمنصتنا.</p>
            <p>فريق عمل Conversion Trust</p>
        </div>
        <div class="footer">
            <p>هذه رسالة تلقائية، يرجى عدم الرد عليها مباشرة.</p>
            <p>&copy; {{ date('Y') }} Conversion Trust. جميع الحقوق محفوظة.</p>
        </div>
    </div>
</body>
</html>

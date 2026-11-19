<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Wex Technology') ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #EAF3FF;
            font-family: Tahoma, Arial, sans-serif;
            color: #1A1A1A;
            line-height: 1.6;
            direction: rtl;
            text-align: right;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #FFFFFF;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(6, 26, 85, 0.08);
        }
        .header {
            background-color: #061A55;
            color: #FFFFFF;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: bold;
            color: #FFFFFF;
        }
        .header .subtitle {
            margin-top: 4px;
            font-size: 13px;
            color: #00B7FF;
        }
        .content {
            padding: 30px 24px;
        }
        .footer {
            background-color: #F4F7FC;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            border-top: 1px solid #E1E8F2;
        }
        .footer p {
            margin: 4px 0;
        }
        .btn {
            display: inline-block;
            background-color: #0B3FBF;
            color: #FFFFFF !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
            margin: 15px 0;
        }
        .code-box {
            background-color: #EAF3FF;
            border: 2px dashed #0B3FBF;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #061A55;
            margin: 20px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #EAF3FF;
            font-size: 14px;
        }
        .info-table .label {
            color: #555555;
            width: 40%;
        }
        .info-table .value {
            font-weight: bold;
            color: #061A55;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ويكس تكنولوجي - Wex Technology</h1>
            <div class="subtitle">مول الواحة، الشهداء، أم درمان</div>
        </div>
        <div class="content">
            <?= $this->renderSection('content') ?: ($content ?? '') ?>
        </div>
        <div class="footer">
            <p>ويكس تكنولوجي (Wex Technology) - أم درمان، السودان</p>
            <p>هذا البريد تم إرساله آلياً، لا تقم بالرد عليه مباشرة.</p>
        </div>
    </div>
</body>
</html>

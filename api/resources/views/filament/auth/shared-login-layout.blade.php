@props([
    'title',
    'subtitle',
])

<div class="shared-login-wrapper">
    <style>
    header.fi-simple-header.flex.flex-col.items-center {
        z-index: 999;
        background: transparent;
        padding-top: 20px;
    }

    header.fi-simple-header.flex.flex-col.items-center .fi-logo {
        height: 125px !important;
        width: auto !important;
    }

    header.fi-simple-header.flex.flex-col.items-center .fi-logo img,
    header.fi-simple-header.flex.flex-col.items-center .fi-logo svg {
        height: 125px !important;
        width: auto !important;
    }

        .fi-simple-main.my-16.w-full {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
        }

        .fi-simple-page section.grid {
            gap: 0 !important;
        }

        html,
        body,
        .fi-simple-layout,
        .fi-simple-page,
        .fi-simple-main {
            height: 100% !important;
            overflow: hidden !important;
        }

        .shared-login-wrapper {
            min-height: 100vh;
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            width: 100%;
            background: #ffffff;
            font-family: Inter, Poppins, system-ui, -apple-system, Segoe UI, sans-serif;
            margin-top: 0;
            position: fixed;
            inset: 0;
        }

        .shared-login-left {
            width: 55%;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 32px;
            box-sizing: border-box;
        }

        .shared-login-left-inner {
            width: 100%;
            max-width: 520px;
            text-align: center;
        }

        .shared-login-illustration {
            width: 100%;
            height: auto;
            max-height: 500px;
            object-fit: contain;
            display: block;
            margin: 0 auto 20px;
        }

        .shared-login-heading {
            font-size: 28px;
            line-height: 1.25;
            font-weight: 700;
            color: #1f3d2b;
            margin: 0 0 10px 0;
        }

        .shared-login-subtext {
            font-size: 14px;
            line-height: 1.5;
            color: #9aa0a6;
            max-width: 420px;
            margin: 0 auto;
        }

        .shared-login-right {
            width: 45%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 24px;
            box-sizing: border-box;
        }

        .shared-login-card {
            width: 100%;
            max-width: 500px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(20, 30, 25, 0.12);
            padding: 28px 28px 26px;
            box-sizing: border-box;
        }

        .shared-login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
            color: #f0b429;
            font-weight: 700;
            letter-spacing: 0.6px;
        }

        .shared-login-title {
            font-size: 20px;
            line-height: 1.3;
            font-weight: 700;
            color: #1f3d2b;
            text-align: center;
            margin: 0 0 6px 0;
        }

        .shared-login-subtitle {
            font-size: 13px;
            line-height: 1.4;
            color: #8f9b94;
            text-align: center;
            margin: 0 0 18px 0;
        }

        .shared-login-card .fi-input,
        .shared-login-card input,
        .shared-login-card select,
        .shared-login-card textarea {
            border-radius: 10px !important;
            border: 1px solid #dddddd !important;
            box-shadow: none !important;
        }

        .shared-login-card .fi-input:focus,
        .shared-login-card input:focus,
        .shared-login-card select:focus,
        .shared-login-card textarea:focus {
            border-color: #1f3d2b !important;
            outline: none !important;
            box-shadow: 0 0 0 2px rgba(31, 61, 43, 0.12) !important;
        }

        .shared-login-card .fi-btn,
        .shared-login-card button[type="submit"] {
            border-radius: 10px !important;
        }

        .shared-login-card .fi-btn-color-primary,
        .shared-login-card .btn-gradient-primary,
        .shared-login-card button[type="submit"],
        .shared-login-card .fi-btn-primary {
            background: linear-gradient(143deg, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
            color: #1f3d2b !important;
        }

        .shared-login-card .fi-btn-color-primary:hover,
        .shared-login-card .btn-gradient-primary:hover,
        .shared-login-card button[type="submit"]:hover,
        .shared-login-card .fi-btn-primary:hover {
            background: linear-gradient(143deg, #F6C32E 0%, #F0B90B 50%, #D99E00 100%) !important;
        }

        .shared-login-card .fi-fo-field-wrp-error-message,
        .shared-login-card .fi-fo-field-wrp-error-message * {
            color: #8f1d1d !important;
        }

        .shared-login-card .fi-fo-field-wrp-error-message {
            background: #fdecea;
            border: 1px solid #f7c8c4;
            border-radius: 10px;
            padding: 8px 10px;
        }

        .shared-login-footer {
            margin-top: 16px;
            text-align: center;
            font-size: 12px;
            color: #9aa0a6;
        }

        .shared-login-footer a {
            color: #1f3d2b;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .shared-login-wrapper {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }

            .shared-login-left {
                width: 100%;
                order: 2;
                padding: 16px 20px 24px;
                display: flex;
            }

            .shared-login-right {
                width: 100%;
                order: 1;
                padding: 24px 16px;
            }

            .shared-login-card {
                max-width: 100%;
                padding: 24px 22px;
            }

            .shared-login-illustration {
                height: 400px;
                max-height: 400px;
            }
        }

        @media (max-height: 700px) and (min-width: 769px) {
            .shared-login-left {
                padding: 24px 24px;
            }

            .shared-login-illustration {
                max-height: 300px;
                margin-bottom: 14px;
            }

            .shared-login-heading {
                font-size: 24px;
            }
        }
    </style>

    <div class="shared-login-left">
        <div class="shared-login-left-inner">
            <img
                src="{{ asset('images/map-web-login-illustration.png') }}"
                alt="MAP - Streamline Your Institute Operations"
                class="shared-login-illustration"
            >
            <h1 class="shared-login-heading">Streamline Your Institute Operations.</h1>
            <p class="shared-login-subtext">
                The most advanced hostel management system designed for scale, powering institutions across India.
            </p>
        </div>
    </div>

    <div class="shared-login-right">
        <div class="shared-login-card">
            <div class="shared-login-title">{{ $title }}</div>
            <div class="shared-login-subtitle">{{ $subtitle }}</div>

            {{ $slot }}
        </div>
    </div>
</div>

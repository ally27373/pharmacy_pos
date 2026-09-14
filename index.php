<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Welcome</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #0f172a;
            color: #ffffff;
            overflow: hidden;
        }

        .landing {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;

            background:
                radial-gradient(
                    circle at 20% 20%,
                    rgba(59, 130, 246, 0.25),
                    transparent 35%
                ),
                radial-gradient(
                    circle at 80% 80%,
                    rgba(14, 165, 233, 0.18),
                    transparent 35%
                ),
                #0f172a;
        }

        .landing-content {
            width: 100%;
            max-width: 600px;
            text-align: center;

            animation: fadeUp 0.8s ease forwards;
        }

        .logo {
            margin-bottom: 35px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 4px;
            color: #93c5fd;
        }

        h1 {
            font-size: clamp(3rem, 10vw, 6rem);
            line-height: 1;
            margin-bottom: 20px;
            font-weight: 800;
        }

        .subtitle {
            font-size: clamp(1rem, 3vw, 1.25rem);
            color: #94a3b8;
            margin-bottom: 45px;
        }

        .start-button {
            border: none;
            outline: none;
            cursor: pointer;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 18px;

            padding: 17px 30px;
            min-width: 180px;

            border-radius: 999px;

            background: #2563eb;
            color: #ffffff;

            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 1px;

            box-shadow:
                0 10px 30px rgba(37, 99, 235, 0.3);

            transition:
                transform 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .start-button:hover {
            background: #3b82f6;
            transform: translateY(-3px);

            box-shadow:
                0 15px 35px rgba(37, 99, 235, 0.4);
        }

        .start-button:active {
            transform: translateY(0);
        }

        .arrow {
            font-size: 1.4rem;
            transition: transform 0.2s ease;
        }

        .start-button:hover .arrow {
            transform: translateX(5px);
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(25px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 600px) {
            .landing {
                padding: 20px;
            }

            .logo {
                margin-bottom: 25px;
            }

            .subtitle {
                margin-bottom: 35px;
            }

            .start-button {
                width: 100%;
                max-width: 280px;
            }
        }
    </style>
</head>

<body>

    <main class="landing">

        <div class="landing-content">

            <div class="logo">
                YOUR LOGO
            </div>

            <h1>
                Welcome
            </h1>

            <p class="subtitle">
                Welcome to our system
            </p>

            <button
                id="startButton"
                class="start-button"
                type="button"
            >
                <span>START</span>
                <span class="arrow">→</span>
            </button>

        </div>

    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const startButton =
                document.getElementById("startButton");

            startButton.addEventListener("click", function () {

                window.location.href = "/auth/login.php";

            });

        });
    </script>

</body>
</html>

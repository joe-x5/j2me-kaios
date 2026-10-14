<?php
$message = '';
$messageType = '';

$targetDir = __DIR__ . '/jars/';
$listFile = __DIR__ . '/list.json';

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

function refreshListFile($targetDir, $listFile) {
    $jarFiles = glob($targetDir . '*.jar') ?: [];
    $relativePaths = [];

    foreach ($jarFiles as $file) {
        $relativePaths[] = 'jars/' . basename($file);
    }

    file_put_contents(
        $listFile,
        implode(PHP_EOL, $relativePaths) . ($relativePaths ? PHP_EOL : ''),
        LOCK_EX
    );
}

function getUploadedGames($listFile) {
    if (!is_file($listFile)) {
        return [];
    }

    return file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload'])) {
        if (
            !isset($_FILES['fileToUpload']) ||
            $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK
        ) {
            $message = 'Please choose a valid file to upload.';
            $messageType = 'error';
        } else {
            $originalName = basename($_FILES['fileToUpload']['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if ($extension !== 'jar') {
                $message = 'Only JAR files are allowed.';
                $messageType = 'error';
            } else {
                // Replace unsafe filename characters.
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
                $destination = $targetDir . $safeName;

                if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $destination)) {
                    refreshListFile($targetDir, $listFile);
                    $message = '“' . $safeName . '” was uploaded successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'There was an error uploading the file.';
                    $messageType = 'error';
                }
            }
        }
    }

    if (isset($_POST['refresh'])) {
        refreshListFile($targetDir, $listFile);
        $message = 'The JAR list has been updated.';
        $messageType = 'success';
    }
}

$uploadedGames = getUploadedGames($listFile);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload JAR Games</title>

    <style>
        :root {
            color-scheme: light;
            --page-bg: #f2f5fb;
            --card-bg: #ffffff;
            --text: #182235;
            --muted: #63718a;
            --accent: #536dfe;
            --accent-dark: #4055d6;
            --border: #dce3ef;
            --focus: #a9b5ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
            background:
                radial-gradient(circle at top left, #e4eaff 0, transparent 38%),
                var(--page-bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        .page {
            width: min(100%, 760px);
            margin: 0 auto;
            animation: appear 500ms ease both;
        }

        header {
            margin-bottom: 24px;
            text-align: center;
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(1.7rem, 5vw, 2.4rem);
            letter-spacing: -0.04em;
        }

        .intro {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .card {
            margin-bottom: 18px;
            padding: 24px;
            background: var(--card-bg);
            border: 1px solid rgba(220, 227, 239, 0.8);
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(35, 51, 86, 0.08);
            animation: rise 450ms ease both;
        }

        .card h2 {
            margin: 0 0 16px;
            font-size: 1.15rem;
        }

        .field-label {
            display: block;
            margin-bottom: 9px;
            font-weight: 700;
        }

        input[type="file"] {
            display: block;
            width: 100%;
            margin-bottom: 16px;
            padding: 12px;
            color: var(--muted);
            background: #f8f9fd;
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: border-color 180ms ease, background-color 180ms ease,
                        box-shadow 180ms ease;
        }

        input[type="file"]:hover {
            background: #f0f3ff;
            border-color: var(--accent);
        }

        input[type="file"]:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 3px;
        }

        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 11px 18px;
            color: #fff;
            font: inherit;
            font-weight: 700;
            background: var(--accent);
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            transition: background-color 180ms ease, transform 180ms ease,
                        box-shadow 180ms ease;
        }

        button:hover {
            background: var(--accent-dark);
            box-shadow: 0 6px 16px rgba(83, 109, 254, 0.25);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .refresh-button {
            background: #16856b;
        }

        .refresh-button:hover {
            background: #106b56;
            box-shadow: 0 6px 16px rgba(22, 133, 107, 0.22);
        }

        .message {
            margin-bottom: 18px;
            padding: 13px 15px;
            border-radius: 10px;
            animation: appear 250ms ease both;
        }

        .message.success {
            color: #126044;
            background: #e7f7ef;
            border: 1px solid #bde8d2;
        }

        .message.error {
            color: #9a3030;
            background: #fff0f0;
            border: 1px solid #f2c4c4;
        }

        .game-list {
            display: grid;
            gap: 9px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .game-list li {
            overflow-wrap: anywhere;
            padding: 11px 13px;
            color: #34415b;
            background: #f7f8fc;
            border: 1px solid #e8ecf4;
            border-radius: 9px;
            transition: color 180ms ease, background-color 180ms ease,
                        transform 180ms ease;
        }

        .game-list li:hover {
            color: var(--accent-dark);
            background: #eef1ff;
            transform: translateX(3px);
        }

        .empty-state {
            margin: 0;
            color: var(--muted);
        }

        @keyframes appear {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Compact layout for very narrow or short viewports */
        @media (max-width: 240px), (max-height: 240px) {
            body {
                min-height: auto;
                padding: 8px;
            }

            header {
                margin-bottom: 10px;
                text-align: left;
            }

            h1 {
                font-size: 1.25rem;
            }

            .intro {
                font-size: 0.85rem;
            }

            .card {
                margin-bottom: 10px;
                padding: 12px;
                border-radius: 10px;
            }

            .card h2 {
                margin-bottom: 10px;
                font-size: 1rem;
            }

            input[type="file"] {
                padding: 8px;
                font-size: 0.8rem;
            }

            button {
                width: 100%;
                min-height: 38px;
                padding: 8px 10px;
                font-size: 0.85rem;
            }

            .game-list li {
                padding: 8px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <header>
            <h1>Upload Your Java JAR Games</h1>
            <p class="intro">Upload a JAR file and keep your game list up to date.</p>
        </header>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"
                 role="status">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2>Upload a game</h2>
            <form action="" method="post" enctype="multipart/form-data">
                <label class="field-label" for="fileToUpload">Choose a JAR file</label>
                <input
                    type="file"
                    name="fileToUpload"
                    id="fileToUpload"
                    accept=".jar"
                    required
                >
                <button type="submit" name="upload" value="1">Upload JAR</button>
            </form>
        </section>

        <section class="card">
            <h2>Refresh the game list</h2>
            <form action="" method="post">
                <button class="refresh-button" type="submit" name="refresh" value="1">
                    Update List
                </button>
            </form>
        </section>

        <section class="card">
            <h2>Uploaded Games</h2>

            <?php if ($uploadedGames): ?>
                <ul class="game-list">
                    <?php foreach ($uploadedGames as $game): ?>
                        <li><?= htmlspecialchars($game, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-state">No games have been uploaded yet.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
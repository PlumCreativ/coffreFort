<!DOCTYPE html>

<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        
        <title>CryptoVault - Dashboard</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <link rel="stylesheet" href="style.css">
    </head>

    <body>
        <header>
            <nav class="navbar">                
                <!-- Icon -->
                <div class="logo">
                    <img id="img-logo" src="img/logo.jpeg" alt="Logo"></img>
                </div>
            </nav>
        </header>

        <!-- Hero Section -->
        <section class="hero py-4 container-fluid">
            <h1>Welcome to CryptoVault</h1>
            <p>Your secure file storage solution</p>
        </section>

        <!-- File Card -->
        <section class="file-section fs-5 container-fluid">
            <div class="file-card">

                <div class="file-icon">
                    <!-- Icône fichier -->
                    <img id="img-download" src="img/file_download.svg">
                </div>

                <div class="file-content text-center pt-2 pb-3">
                    <h3 id="file-name"> Chargement...</h3>
                    <!-- <p id="file-description"></p> -->
                </div>

                <!-- message d’erreur => caché par défaut -->
                <div id="error-box" class="my-3 mx-0 p-1 fw-bold text-center">
                </div>

                <div class="file-info fs-6">
                    <!-- <p><strong>Auteur du fichier :</strong> <span id="file-author"></span></p> -->
                    <p class= "py-1 pt-3"><strong>Taille du fichier :</strong> <span id="file-size"></span></p>
                    <p class= "py-1"><strong>Créé le :</strong> <span id="file-date"></span></p>

                    <!-- expiration -->
                    <p class= "py-1"><strong>Expiration :</strong> <span id="expires-left">-</span></p>

                    <!-- optionnel: restant => implémenter côté JS plus tard -->
                    <!-- <p><strong>Restant :</strong> <span id="uses-left">-</span></p> -->
                </div>

                <div class="file-actions d-flex justify-content-center">
                    <a id="dl-link" href="#" class="btn-link">Télécharger</a>
                    <!-- <a href="#link" class="btn-link">Partager</a> -->
                </div>
            </div>
        </section>

        <!-- Files header -->
        <section class="files-header container-fluid">
            <h2>Your Files</h2>
            <p>Manage and access your files securely</p>
        </section>

        <!-- il faut mettre dans share.js!!!! -->
        <script src="share.js"></script>
    </body>
</html>

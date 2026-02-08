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
                    <p class= "py-1 pt-3"><strong>Taille :</strong> <span id="file-size"></span></p>
                    <p class= "py-1"><strong>Créé le :</strong> <span id="file-date"></span></p>

                    <!-- badge et version courant => à afficher si 'versions_count > 1'-->
                    <div id= "versions-box" class="py-1">
                        <span class="badge bg-info text-dark me-2" id="version-badge">
                            <i class="bi bi-clock-history"></i> <span id="version-count">0</span> versions 
                        </span>
                        <span class="text-muted">
                            <strong>Version courante : </strong>
                            <span class="" id= "current-version-date"> - </span>
                        </span>
                    </div>

                    <!-- sélécteur => quand ça va être autorisé -->
                    <div id="version-picker-wrap" class="mt-3">
                        <label for="version-picker" class="form-label mb-1">
                            <i class="bi bi-list-ol"></i> Choisir une version à télécharger
                        </label>
                        <select id="version-picker" class="form-select form-select-sm">
                            <option value="">Version courante</option>
                        </select> 
                        <div class="form-text text-muted" >
                            Sélectionnez une version spécifique à télécharger.
                        </div>
                        <!-- <div class="form-text" id= "version-picker-helper">
                            <p>Certaines versions peuvent ne pat être disponible publiquement.</p>
                        </div> -->
                    </div>

                    <!-- message si versions existent mais sélecteur est désactivé -->
                    <div class="mt-2 alert alert-info py-2" id="versions-info-only">
                        <small class="text-muted">
                            <i class="bi bi-info-cercle"></i>
                            Ce fichier possède plusieurs versions, mais ce lien ne permet pas de choisir une version spécifique.
                        </small>
                    </div>

                    <div id="folder-files-list" class="mb-2"></div>

                    <!-- expiration et téléchargement restant-->
                    <p class= "py-1"><strong>Expiration :</strong> <span id="expires-left">-</span></p>
                    <p class= "py-1"><strong> Téléchargement restant : </strong> <br><span id="uses-left">-</span></p>
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

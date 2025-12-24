<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>CryptoVault - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <nav class="navbar">                
            <!-- Icon -->
            <div class="logo">
                <img src="img/logo.jpeg" width="100" height="100" alt="Logo">
                </img>
            </div>
        </nav>
    </header>
    <!-- Navbar
    <nav class="navbar">
        <div class="nav-left">CryptoVault</div>
        <div class="nav-right"><a href="#">Home</a></div>
    </nav> -->

    <!-- Hero Section -->
    <section class="hero">
        <h1>Welcome to CryptoVault</h1>
        <p>Your secure file storage solution</p>
    </section>

    <!-- File Card -->
    <section class="file-section">
        <div class="file-card">

            <div class="file-icon">
                <!-- Icône fichier -->
                <img src="img/file_download.svg" style="width: 10em; height: 10em;">

            </div>

            <div class="file-content">
                <h3 id="file-name"></h3>
                <p id="file-description"></p>
            </div>

            <div class="file-info">
                <p><strong>Auteur du fichier :</strong> <span id="file-author"></span></p>
                <p><strong>Taille du fichier :</strong> <span id="file-size"></span></p>
                <p><strong>Créé le :</strong> <span id="file-date"></span></p>
            </div>

            <div class="file-actions">
                <a id="dl-link" href="" class="btn-link">Télécharger</a>
                <a href="#link" class="btn-link">Partager</a>
            </div>
        </div>
    </section>

    <!-- Files header -->
    <section class="files-header">
        <h2>Your Files</h2>
        <p>Manage and access your files securely</p>
    </section>

<script>
    const fileId = 1; // ID du fichier que tu veux afficher

    fetch(`/files/${fileId}`)
        .then(r => r.json())
        .then(file => {
            console.log("File data:", file);

            // Mise à jour du DOM
            document.querySelector('#file-name').textContent = file.original_name;
            document.querySelector('#file-description').textContent = file.description ?? "Via ce lien vous pouvez télécharger ou partager le lien reçu.";
            document.querySelector('#file-author').textContent = file.user_name ?? "N/A";
            document.querySelector('#file-size').textContent = (file.size / 1024 / 1024).toFixed(2) + " MB";
            document.querySelector('#file-date').textContent = file.created_at;
            document.querySelector('#dl-link').href = `/files/${file.id}/download`;
        })
        .catch(err => console.error(err));
</script>


</body>
</html>

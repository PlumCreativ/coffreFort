// const { use } = require("react");

function humanSize(bytes) {

    if (bytes == null) return "-";

    const units = ["B", "KB", "MB", "GB", "TB"];
    let i = 0;
    let v = bytes;

    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }

    //i est en octet 532 B => pas de décimales
    //sinon 2 décimales p.ex. 3.03 MB
    return `${v.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
}


function daysLeft(expiresAt){
    if(!expiresAt) return null;

    // "2025-12-26T09:39:54Z" => interprétation 26 décembre à 09:39:54 UTC.
    const exp = new Date(expiresAt.replace(" ", "T") + "Z");

    const now = new Date();

    const diffMilliSecondes = exp - now;
    //si diffMilliSecondes > 0 => expiration dans la future

    //1000 ms = 1 seconde
    // 60 s = 1 minute
    // 60 min = 1 heure
    // 24 h = 1 jour
    //.ceil => arrondissement ver le haut
    const day = Math.ceil(diffMilliSecondes/ (1000 * 60 * 60 * 24));
    return day;
}

function setText(sel, value) {
    const element = document.querySelector(sel);
    if (!element) return;
    element.textContent = value ?? "";
}

function hide(sel) {
    const el = document.querySelector(sel);
    if (!el) return;
    el.style.display = "none";
}

function show(sel) {
    const el = document.querySelector(sel);
    if (!el) return;
    el.style.display = "";
}

function getToken() {
  return new URLSearchParams(window.location.search).get("token");
}

function formatDateTime(sqlDate) {
    if(!sqlDate) return "-";

    // sqlDate: "2025-12-26 09:39:54"

    const date = new Date(sqlDate.replace(" ", "T"));
    if(isNaN(date.getTime())){
        return sqlDate;
    }

    return date.toLocaleDateString("fr-FR");
}

function showError(message) {
    const box = document.querySelector("#error-box");
    if (box) {
        box.textContent = message;
        box.style.display = "block";
    }
}

//============== gestion des versions ======================

//charger la liste des versions et peupler le sélecteur
function loadVersions(token){

    const versionsUrl = `/s/${encodeURIComponent(token)}/versions?limit=50&offset=0`;

    fetch(versionsUrl)
        .then(async resp => {
            const data = await resp.json().catch(() => ({}));
            if(!resp.ok){
                throw new Error(data.error || `HTTP ${resp.status}`);
            } 
            return data;
        })
        .then((vdata) => {

            //peupler le select min version courante only => plus tard via endpoint???
            const select = document.querySelector("#version-picker");

            if(!select) return;

            select.innerHTML = "";

            //placeholder => version courante => download sans ?v=
            const defaultOption = document.createElement("option");
            defaultOption.value = "";
            defaultOption.textContent = "Version courante";
            select.appendChild(defaultOption);

            //ajouter toutes les versiosn
            const versions = vdata.versions || [];
            versions.forEach(v => {
                const option = document.createElement("option");
                option.value = String(v.version);
                option.textContent = `v${v.version} - ${formatDateTime(v.created_at)} (${humanSize(v.size)})`;

                //marquer la version courante
                if(v.is_current) {
                    option.textContent += " ⭐ (actuelle)";
                }
                select.appendChild(option); 
            });

            // pour éviter d'emplier en cas de rechargement
            select.onchange = () => {
                updateDownloadLink(token, select.value);
                
                // const v = select.value;
                //autorisation sur ?v= sur download => future
                // const dl = document.querySelector("#dl-link");
                // const base = `/s/${encodeURIComponent(token)}/download`;
                // dl.href = v ? `${base}?v=${encodeURIComponent(v)}` : base;

                console.log("Version sélectionné: ", select.value || "courante");
            };
        })
        .catch((err) => {
            console.error("Erreur chargement versions: ", err);
            hide("#version-picker-wrap");
            show("#versions-info-only");
        });
}

//mise à jour le lien de téléchargement avec la version sélectionnée
function updateDownloadLink(token, version){

    const downloadBtn = document.querySelector("#dl-link");
    if(!downloadBtn) return;
    
    const baseUrl = `/s/${encodeURIComponent(token)}/download`;
    const downloadUrl = version ? `${baseUrl}?v=${encodeURIComponent(version)}` : baseUrl;

    // Mettre à jour le href et attribut data
    downloadBtn.href = downloadUrl;
    downloadBtn.setAttribute("data-download-url", downloadUrl);

    console.log("Lien mise à jour", downloadUrl);

}


// ==================== INITIALISATION ====================


const token = getToken();

if (!token) {
    setText("#file-name", "Token manquant");
    showError("Token manquant dans l'URL");
    throw new Error("Token manquant");
}

const metaurl = `/s/${encodeURIComponent(token)}`;

// variable globale pour stocker les métadonnées du fichier
let fileMetadata = null;

fetch(metaurl)
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        if(!response.ok){
            throw new Error(data.error || `HTTP ${response.status}`);
        } 
        return data;
    })

    .then(share => {
        console.log("Métadonnées reçues:", share);

        const kind = share.kind; // 'file' ou 'folder' <=> si je mets share.kind || 'file' => ça va forcer le type fichier même pour les dossiers => pas bon
        const metaData = share.meta || {};
       
        let displayName;
        let fileSize;
        let createdAt;

        if(kind === 'file'){
            displayName = metaData.original_name || share.label || "Fichier partagé";
            fileSize = metaData.size;
            createdAt = metaData.created_at;

             //gestion des versions
            const versionsCount = metaData.versions_count ?? 0;
            const currentVersion = metaData.current_version || null;
            //sélecteur si exposition publique va être autorisée => actuellement .......????????
            const allowFixedVersions = share.allow_fixed_versions === true;

            handleFileVersions(versionsCount, currentVersion, allowFixedVersions, token);
        }else if (kind === 'folder'){
            displayName = metaData.name || share.label || "Dossier partagé";
            fileSize = metaData.total_size;
            createdAt = metaData.created_at;

            //afficher la liste des fichiers du dossier
            displayFolderContents(metaData.files || []);

            //masquer la section des versions pour les dossiers
            hide("#versions-box");
            hide("#version-picker-wrap");
            hide("#versions-info-only");
        }

        //affichage commun pour les fichiers et dossiers
        setText("#file-name", displayName);                                  // nom affiché
        setText("#file-size", humanSize(fileSize));                          // taille
        setText("#file-date", createdAt ? formatDateTime(createdAt) : "-");  // date de création

        //expiration
        handleExpiration(share.expires_at);

        //téléchargement restant
        handleRemainingUses(share.max_uses, share.remaining_uses);

        //config du lien de téléchargement
        setupDownloadLink(token);

    })    
    .catch(err => {
        console.log("Erreur chargement métadonnées: ", err);

        setText("#file-name", "Lien invalide ou expiré");
        setText("#file-size", "-");
        setText("#file-date", "-");
        hide("#dl-link");

        showError(err.message || "Impossible de charger les informations du partage.");
    });

    //gestion les versions d'un fichier partagé (uniquement pour les partages de type fichier)
    function handleFileVersions(versionsCount, currentVersion, allowFixedVersions, token){

        hide("#versions-box");
        hide("#version-picker-wrap");
        hide("#versions-info-only");

        //s'il y a plusieurs versions
        if(versionsCount > 1) {

            //affichage le box avec le nbre de versions
            show("#versions-box");
            setText("#version-count", versionsCount);

            if(currentVersion && currentVersion.created_at){
                setText("#current-version-date", formatDateTime(currentVersion.created_at));
            }else{
                setText("#current-version-date", "-");
            }

            // si les versions fixew sont autorisées
            if(allowFixedVersions){
                show("#version-picker-wrap");
                hide("#versions-info-only");

                // charger la liste des versions
                loadVersions(token);
            } else{
                hide("#version-picker-wrap");
                show("#versions-info-only");
            }
        }
    }

    //affichage du contenu du dossier partagé
    function displayFolderContents(files){
       
        //section pour afficher la liste des fichiers d'un dossier partagé => à faire!!
        const filesList = document.querySelector("#folder-files-list");
        if(!filesList) return;

        if(files.length === 0){
            filesList.innerHTML = "<p>Dossier vide</p>";
            return;
        }

        filesList.innerHTML = files.map(file => `
            <div class="file-item">
                <p>
                    <span class="file-name"><strong>${file.name}</strong></span>
                    <span class="file-size fst-italic">${humanSize(file.size)}</span>
                    <span class="file-date fst-italic">${formatDateTime(file.created_at)}</span>
                </p>
            </div>
        `).join("");
        
        show("#folder-files-list");
    }

    //gestion de l'expiration d'un partage
    function handleExpiration(expiresAt){

        //expiration
        const left = daysLeft(expiresAt);
        if(left != null){
            const txt = left <= 0 ? "Expiré" : `Expire dans ${left} jour(s)`;
            setText("#expires-left", txt);

            const expiresEl = document.querySelector("#expires-left");

            if(expiresEl){
                if(left <= 1){
                    expiresEl.style.color = "red";
                    expiresEl.style.fontWeight = "bold";
                }else if(left <= 3){
                    expiresEl.style.color = "orange";
                    expiresEl.style.fontWeight = "bold";
                }
            }
        }else{
            setText("#expires-left", "Jamais");
        }
    }

    //gestion des téléchargements restants
    function handleRemainingUses(maxUses, remainingUses){
       
        if(maxUses !== null && remainingUses !== null) {
            const remaining = parseInt(remainingUses);
            const max = parseInt(maxUses);

            let usesText;
            if(remaining <= 0){
                usesText = "Aucun téléchargement restant";
            } else if (remaining === 1){
                usesText = "1 téléchargement restant";
            }else{
                usesText = `${remaining} / ${max} téléchargement(s) restant(s)`;
            }
            setText("#uses-left", usesText);
            const usesEl = document.querySelector("#uses-left");

            //changer la couleur
            if(usesEl){
                if(remaining <= 1){
                    usesEl.style.color = "red";
                    usesEl.style.fontWeight = "bold";
                }else if( remaining <= 3){
                    usesEl.style.color = "orange";
                    usesEl.style.fontWeight = "bold";
                }
            }
        }else{
            setText("#uses-left","Illimité");
        }
    }


    //config du lien de téléchargement
    function setupDownloadLink(token){
       
        const downloadBtn = document.querySelector("#dl-link");
        if (downloadBtn) {
            const downloadUrl = `/s/${encodeURIComponent(token)}/download`;
            downloadBtn.href = downloadUrl;
            downloadBtn.setAttribute("data-download-url", downloadUrl);
            show("#dl-link");
        }
    }



//=============== gestion de téléchargement ======================

document.addEventListener("DOMContentLoaded", () => {

    const downloadLink = document.querySelector("#dl-link");
    if(!downloadLink) return;

    downloadLink.addEventListener("click", async(e) => {
        e.preventDefault();

        //reset UI error
        const errorBox = document.querySelector("#error-box");
        if (errorBox) {
            errorBox.textContent = "";
            errorBox.style.display = "none";
        }

        try{
            const downloadUrl = downloadLink.getAttribute("data-download-url") || downloadLink.href;

            const response = await fetch(downloadUrl, {

                //pour le redirection ou proxies au cas ou
                redirect : "follow", 
                cache: "no-store"
            });

            // si erreur HTTP  => lire json erreur ou texte
            if(!response.ok){
                let message = `HTTP ${response.status}`;
                const contentType = response.headers.get("Content-Type") || "";

                if(contentType.includes("application/json")){
                    const data = await response.json().catch(() => ({}));
                    message = data.error || message;
                } else{
                    const text = await response.text().catch(() => "");
                    if (text){
                        message = text.slice(0, 200);
                    }
                }
                throw new Error(message);
            }

            //vérification si c'est bien un fichier binaire
            const contentType = (response.headers.get("Content-Type") || "").toLowerCase();

            if(contentType.includes("application/json") || contentType.includes("text/html")){

                const txt = await response.text().catch(() => "");
                throw new Error(
                    "Le serveur n'a pas renvoyé le fichier (réponse: " +
                    (txt ? txt.slice(0, 160) : contentType) +
                    ")"
                );
            } 

            console.log("DOWNLOAD status", response.status);
            console.log("DOWNLOAD content-type", response.headers.get("Content-Type"));
            console.log("DOWNLOAD content-length", response.headers.get("Content-Length"));
            console.log("DOWNLOAD disposition", response.headers.get("Content-Disposition"));

            //ok
            //transforme le contenu reçu (PDF, image, zip, etc.) en Blob 
            //=> un objet JavaScript qui représente des données binaires, donc un “fichier” en mémoire
            // télécharger le fichier dans le code js au lieu que le navigateur ouvre directement url
            const blob = await response.blob();

            // essayer de récupérer le nom de fichier depuis Content-Disposition
            let filename = (fileMetadata && fileMetadata.original_name) ? fileMetadata.original_name : "download";
            
            const contentDisposition  = response.headers.get("Content-Disposition") || "";  //p.ex. attachment, MonFichier.pdf

            // capture de filename* = UTF-8'' => jusqu'au ;
            const mStar = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(contentDisposition); 

            //chercher la variante filename=.. avec ou sans guillements
            const m = /filename\s*=\s*"([^"]+)"/i.exec(contentDisposition) || 
                        /filename\s*=\s*([^;]+)/i.exec(contentDisposition);
            
            if(mStar && mStar[1]){ // filename="Poupee.pdf"
                filename = decodeURIComponent(mStar[1]);  //Poupee.pdf
            }else if (m && m[1]){
                filename = m[1].trim().replace(/^"(.*)"$/, "$1");
            }

            //créer un lien temporaire pour déclencher le téléchargement
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = filename;

            document.body.appendChild(a);
            a.click();
            a.remove();
            //window.URL.revokeObjectURL(url);

            //libérer l'url après un délai
            // pour éviter le revoke trop vite (sinon certains navigateurs tronquent) ????
            setTimeout(() => window.URL.revokeObjectURL(url), 2000);

        }catch(err){
            const msg = (err && err.message) ? err.message : "Erreur inconnue";

            //afficher pour utilisateur
            showError(msg);
            console.error("Erreur téléchargement : ", err);
        }
    });
});
    
     
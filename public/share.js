
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

const token = getToken();

if (!token) {
  setText("#file-name", "Token manquant");
  throw new Error("Token manquant");
}

const metaurl = `/s/${encodeURIComponent(token)}`;

fetch(metaurl)
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        if(!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
        return data;
    })

    .then(meta => {
        const file = meta.file || null;

        //nom affiché
        const displayName = (file && file.original_name) || meta.label || "Ressource partage";

        setText("#file-name", displayName);

        //taille
        setText("#file-size", humanSize(file?.size));

        // date
        setText("#file-date", file?.created_at || "-");


        //versions UI
        const versionsCount = file?.versions_count ?? 0;
        const currentVersionDate = file?.current_version?.created_at ?? null;

        //par défaut tout qui est caché
        // sécurité
        hide("#versions-box");
        hide("#version-picker-wrap");
        hide("#versions-info-only");

        if(versionsCount > 1) {
            show("#versions-box");
            setText("#current-version-date", formatDateTime(currentVersionDate));

            //sélecteur si exposition publique va être autorisée => actuellement .......????????
            const allowPublicVersionPick = meta.allow_public_version_pick === true;

            if(allowPublicVersionPick){
                show("#version-picker-wrap");
                hide("#versions-info-only");

                const versionsUrl = `/s/${encodeURIComponent(token)}/versions?limit=50&offset=0`;

                fetch(versionsUrl)
                    .then(async resp => {
                        const data = await resp.json().catch(() => ({}));
                        if(!resp.ok) throw new Error(data.error || `HTTP ${resp.status}`);
                        return data;
                    })

                    .then((vdata) => {
                        //peupler le select min version courante only => plus tard via endpoint???
                        const sel = document.querySelector("#version-picker");

                        if(!sel) return;

                        sel.innerHTML = "";

                        //placeholder => version courante => download sans ?v=
                        const placeholder = document.createElement("option");
                        placeholder.value = "";
                        placeholder.textContent = "Version courante";
                        sel.appendChild(placeholder);

                        (vdata.versions || []).forEach((v) => {
                            const option = document.createElement("option");
                            option.value = String(v.version);
                            option.textContent = `v${v.version} - ${formatDateTime(v.created_at)} (${humanSize(v.size)})`;
                            sel.appendChild(option); 
                        });

                        // pour éviter d'emplier en cas de rechargement
                        sel.onchange = () => {
                            const v = sel.value;

                            //autorisation sur ?v= sur download => future
                            // const dl = document.querySelector("#dl-link");
                            // const base = `/s/${encodeURIComponent(token)}/download`;
                            // dl.href = v ? `${base}?v=${encodeURIComponent(v)}` : base;

                            console.log("Version sélectionné: ", v);
                        };
                    })
                    .catch((err) => {
                        console.error("versions fetch error : ", err);
                        hide("#version-picker-wrap");
                        show("#versions-info-only");
                    })
            }
        }

        // lien de download public
        const dl = document.querySelector("#dl-link");
        if(dl){
            const downloadUrl = `/s/${encodeURIComponent(token)}/download`;
            dl.href = downloadUrl;

            dl.addEventListener("click", async(e) => {
                e.preventDefault();

                //reset UI error
                const box = document.querySelector("#error-box");
                if (box) {
                    box.textContent = "";
                    box.style.display = "none";
                }

                try{
                    const response = await fetch(downloadUrl, {

                        //pour le redirection ou proxies au cas ou
                        redirect : "follow", 
                        cache: "no-store"
                    });

                    // si erreur...  => lire json erreur ou texte
                    if(!response.ok){
                        let message = `HTTP ${response.status}`;
                        const ct = response.headers.get("Content-Type") || "";

                        if(ct.includes("application/json")){
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

                    //vérification si c'est un fichier et non une page HTML/JSON
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
                    let filename = (file && file.original_name) ? file.original_name : "download";
                    
                    const cd = response.headers.get("Content-Disposition") || "";  //p.ex. attachment, MonFichier.pdf

                    // capture de filename* = UTF-8'' => jusqu'au ;
                    const mStar = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(cd); 

                    //chercher la variante filename=.. avec ou sans guillements
                    const m = /filename\s*=\s*"([^"]+)"/i.exec(cd) || /filename\s*=\s*([^;]+)/i.exec(cd);
                    
                    if(mStar && mStar[1]){ // filename="Poupee.pdf"
                        filename = decodeURIComponent(mStar[1]);  //Poupee.pdf
                    }else if (m && m[1]){
                        filename = m[1].trim().replace(/^"(.*)"$/, "$1");
                    }

                    const url = window.URL.createObjectURL(blob);

                    const a = document.createElement("a");
                    a.href = url;
                    a.download = filename;

                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    //window.URL.revokeObjectURL(url);

                    // pour éviter le revoke trop vite (sinon certains navigateurs tronquent) ????
                    setTimeout(() => window.URL.revokeObjectURL(url), 2000);

                }catch(err){
                     const msg = (err && err.message) ? err.message : "Erreur inconnue";

                    //afficher pour utilisateur
                    const box = document.querySelector("#error-box");
                    if(box){
                        box.textContent = msg;
                        box.style.display = "block";
                    }
                    console.error(err)
                }
            });

            show("#dl-link");
        }

        //expiration
        const left = daysLeft(meta.expires_at);
        if(left != null){
            const txt = left <= 0 ? "Expire" : `Expire dans ${left} jour(s)`;

            if(document.querySelector("#expires-left")){
                setText("#expires-left", txt);
            }else{
                console.log("txt");
            }
        }
    })
    .catch((err) => {
        console.log(err);

        setText("#file-name", "Lien invalide ou expiré");
        setText("#file-size", "-");
        setText("#file-date", "-");
        hide("#dl-link");

        //afficher un message d'erreur dans un bloc HTML
        // ex: <div id="error-box"></div>
        if (document.querySelector("#error-box")) {
        document.querySelector("#error-box").textContent = err.message;
        document.querySelector("#error-box").style.display = "block";
        }
    })



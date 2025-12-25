 document.querySelector("#loginForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const data = Object.fromEntries(new FormData(e.target));

    // 1) Envoie des identifiants au backend pour générer le JWT
    const response = await fetch("/auth/login", {
        method: "POST",
        headers: { 
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    });

    const json = await response.json();

    if (json.success) {

        // 2) Stocker correctement le token
        localStorage.setItem("jwt", json.jwt);

        // 3) Redirection
        const token = localStorage.getItem("jwt");

        // window.location.href = "/dashboard?jwt=" + encodeURIComponent(jwt);


        fetch('/dashboard', {
            method: "GET",
            headers: {
                "Authorization": "Bearer " + token
            }
        })
        // .then(r => r.text())
        // .then(html => document.body.innerHTML = html);

        // json = await response.json();
        window.location.href = '/main';

    } else {
        alert(json.error);
    }
});
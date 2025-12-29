const token = localStorage.getItem('jwt');
            
    if (!token) {
        window.location.href = '/auth/login';
    }

    // Appel sécurisé à l'API
    fetch('/dashboard', {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    })
    .then(r => r.json())
    .then(data => {
        console.log('API main data:', data);
    });
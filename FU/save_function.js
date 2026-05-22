// Save expense to database
async function saveExpense(amount, lat, lng, details) {
    const response = await fetch('/FU/save_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'save_expense',
            user: getCurrentUser(),
            amount: amount,
            lat: lat,
            lng: lng,
            details: details
        })
    });
    return await response.json();
}

// Load all user data
async function loadUserData() {
    const response = await fetch(`/FU/save_data.php?action=load_all&user=${getCurrentUser()}`);
    return await response.json();
}

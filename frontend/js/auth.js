// frontend/js/auth.js

// --- LOGIN ---
const loginForm = document.querySelector('#loginForm');

loginForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = loginForm.querySelector('button[type="submit"]');
    
    const email = document.querySelector('#email').value.trim();
    const password = document.querySelector('#password').value;

    if (!email || !password) return alert("Please fill all fields");

    try {
        submitBtn.disabled = true; // Prevent double submission
        submitBtn.textContent = "Logging in...";

        const res = await fetch('/pata-nyumba/backend/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'login', email, password })
        });

        const data = await res.json();

        if (!res.ok) {
            alert(data.error || 'Login failed');
            submitBtn.disabled = false;
            submitBtn.textContent = "Login";
            return;
        }

        //  Consistency: Check keys returned by backend/auth.php
        sessionStorage.setItem('user_role', data.role);
        sessionStorage.setItem('user_name', data.full_name || "User"); 
        sessionStorage.setItem('user_id', data.id);

        window.location.href = '/pata-nyumba/frontend/dashboard.html';

    } catch (err) {
        console.error(err);
        alert('Server error. Check if XAMPP is running.');
        submitBtn.disabled = false;
        submitBtn.textContent = "Login";
    }
});

// --- REGISTER ---
const registerForm = document.querySelector('#registerForm');

registerForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = registerForm.querySelector('button[type="submit"]');

    const full_name = document.querySelector('#full_name').value.trim();
    const email = document.querySelector('#email').value.trim();
    const password = document.querySelector('#password').value;
    const phone = document.querySelector('#phone')?.value.trim() || '';
    const role = document.querySelector('#role')?.value || 'customer';

    if (password.length < 8) return alert("Password must be at least 8 characters");

    try {
        submitBtn.disabled = true;
        submitBtn.textContent = "Creating Account...";

        const res = await fetch('/pata-nyumba/backend/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'register', full_name, email, password, phone, role })
        });

        const data = await res.json();

        if (!res.ok) {
            alert(data.error || 'Registration failed');
            submitBtn.disabled = false;
            submitBtn.textContent = "Register";
            return;
        }

        alert(`${data.message}${role === 'agent' ? '\nNote: Agents must be approved by Admin before logging in.' : ''}`);
        window.location.href = '/pata-nyumba/frontend/login.html';

    } catch (err) {
        console.error(err);
        alert('Server error');
        submitBtn.disabled = false;
        submitBtn.textContent = "Register";
    }
});
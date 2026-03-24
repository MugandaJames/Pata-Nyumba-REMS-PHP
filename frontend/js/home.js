// js/home.js
let allProperties = [];

async function showSection(section) {
    const main = document.getElementById('main-content');
    const home = document.getElementById('home-section');

    // Reset view
    if (section === 'home') {
        home.style.display = 'block';
        const dynamic = document.getElementById('dynamic-area');
        if (dynamic) dynamic.remove();
        return;
    }

    home.style.display = 'none';
    let content = `<div id="dynamic-area" class="container section">`;

    if (section === 'properties') {
        content += `
            <div class="search-container">
                <h2 style="margin-bottom:10px;">Find Your Perfect Home</h2>
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search by name..." oninput="filterProperties()">
                    <input type="text" id="locationInput" placeholder="Location (e.g. Nairobi)..." oninput="filterProperties()">
                    <select id="typeFilter" onchange="filterProperties()">
                        <option value="all">Any Status</option>
                        <option value="rent">To Rent</option>
                        <option value="sale">For Sale</option>
                    </select>
                </div>
            </div>
            <div id="guestPropertyGrid" class="grid">Loading properties...</div>
        `;
        fetchProperties();
    } 
    else if (section === 'about') {
        content += `
            <div class="about-card">
                <h2>About Pata Nyumba</h2>
                <p>Pata Nyumba is Kenya's leading digital real estate ecosystem. We connect property seekers directly with verified agents, ensuring transparency through digital agreements and real-time tracking.</p>
                <p style="margin-top:15px;">Our mission is to simplify the housing market on a platform built for speed and security.</p>
            </div>`;
    } 
    else if (section === 'contact') {
        content += `
            <div class="about-card">
                <h2>Contact Our Team</h2>
                <p>📍 Office: Upper Hill, Nairobi, Kenya</p>
                <p>📞 Phone: +254 700 123 456</p>
                <p>📧 Email: info@patanyumba.com</p>
                <div style="margin-top:20px;">
                    <button class="btn btn-primary">Send us a Message</button>
                </div>
            </div>`;
    }

    content += `</div>`;
    
    const existingDynamic = document.getElementById('dynamic-area');
    if (existingDynamic) existingDynamic.remove();
    main.insertAdjacentHTML('beforeend', content);
}

async function fetchProperties() {
    try {
        // FIX: Absolute path to the backend
        const res = await fetch('/pata-nyumba/backend/properties.php');
        
        if (!res.ok) throw new Error("Could not find properties.php");

        allProperties = await res.json();
        renderProperties(allProperties);
    } catch (err) {
        console.error("Fetch error:", err);
        const grid = document.getElementById('guestPropertyGrid');
        if (grid) grid.innerHTML = `<p style="color:red">Unable to load properties. Ensure XAMPP is running.</p>`;
    }
}

function renderProperties(props) {
    const grid = document.getElementById('guestPropertyGrid');
    if (!grid) return;

    if (props.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; padding: 40px;"><p>No properties match your criteria.</p></div>';
        return;
    }

    grid.innerHTML = props.map(p => `
        <div class="card property-card">
            <img src="/pata-nyumba/${p.image_path || 'css/no-image.png'}" alt="Home" style="width:100%; height:180px; object-fit:cover; border-radius:8px;">
            <div style="padding-top:15px;">
                <span class="badge ${p.property_type}">${p.property_type.toUpperCase()}</span>
                <h3 style="margin: 10px 0 5px;">${p.title}</h3>
                <p style="color:#666; font-size:14px;">📍 ${p.location}</p>
                <p class="price" style="color:#3b82f6; font-size:1.2rem; font-weight:bold; margin:10px 0;">KES ${Number(p.price).toLocaleString()}</p>
                <a href="login.html" class="btn btn-primary" style="width:100%; display:block; text-align:center;">Request to ${p.property_type === 'rent' ? 'Rent' : 'Buy'}</a>
            </div>
        </div>
    `).join('');
}

function filterProperties() {
    const nameTerm = document.getElementById('searchInput').value.toLowerCase();
    const locTerm = document.getElementById('locationInput').value.toLowerCase();
    const typeTerm = document.getElementById('typeFilter').value;

    const filtered = allProperties.filter(p => {
        const matchesName = p.title.toLowerCase().includes(nameTerm);
        const matchesLoc = p.location.toLowerCase().includes(locTerm);
        const matchesType = typeTerm === 'all' || p.property_type === typeTerm;
        return matchesName && matchesLoc && matchesType;
    });

    renderProperties(filtered);
}
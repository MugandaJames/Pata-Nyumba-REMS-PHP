
// User Session and Authentication
const userRole = sessionStorage.getItem('user_role');
const userName = sessionStorage.getItem('user_name');
const userId   = sessionStorage.getItem('user_id');

// Global safety check
if (!userRole || !userId) {
    window.location.href = 'login.html';
}

// Sanitizing output to prevent XSS in the header
document.getElementById('userName').textContent = userName;

// Logout Handler
document.getElementById('logoutBtn').addEventListener('click', () => {
    sessionStorage.clear();
    window.location.href = 'login.html';
});

const sidebar = document.getElementById('sidebarMenu');
const dashboardContent = document.getElementById('dashboardContent');

// Utility for UI feedback
function setActive(btnId) {
    document.querySelectorAll("#sidebarMenu button").forEach(btn => btn.classList.remove("active"));
    const activeBtn = document.getElementById(btnId);
    if (activeBtn) activeBtn.classList.add("active");
}

// Role based panel loader

function loadAdminPanel() {
    sidebar.innerHTML = `
        <li><button id="allUsersBtn">👥 Manage Users</button></li>
        <li><button id="approveAgentsBtn">👤 Pending Agents</button></li>
        <li><button id="approvePropertiesBtn">🏠 Pending Properties</button></li>
        <li><button id="viewRequestsBtn">📩 All Requests</button></li>
        <li><button id="viewAgreementsBtn">📄 Agreements</button></li>
    `;

    document.getElementById('allUsersBtn').onclick = () => { setActive('allUsersBtn'); loadAllUsers(); };
    document.getElementById('approveAgentsBtn').onclick = () => { setActive('approveAgentsBtn'); loadApproveAgents(); };
    document.getElementById('approvePropertiesBtn').onclick = () => { setActive('approvePropertiesBtn'); loadApproveProperties(); };
    document.getElementById('viewRequestsBtn').onclick = () => { setActive('viewRequestsBtn'); loadRequests(); };
    document.getElementById('viewAgreementsBtn').onclick = () => { setActive('viewAgreementsBtn'); loadAgreements(); };
    
    loadAllUsers(); // Default view
}

function loadAgentPanel() {
    sidebar.innerHTML = `
        <li><button id="uploadPropertyBtn">➕ Add Property</button></li>
        <li><button id="viewPropertiesBtn">🏠 My Listings</button></li>
        <li><button id="viewRequestsBtn">📩 Client Requests</button></li>
        <li><button id="viewAgreementsBtn">📄 My Agreements</button></li>
    `;

    document.getElementById('uploadPropertyBtn').onclick = () => { setActive('uploadPropertyBtn'); showPropertyForm(); };
    document.getElementById('viewPropertiesBtn').onclick = () => { setActive('viewPropertiesBtn'); loadAgentProperties(); };
    document.getElementById('viewRequestsBtn').onclick = () => { setActive('viewRequestsBtn'); loadRequests(); };
    document.getElementById('viewAgreementsBtn').onclick = () => { setActive('viewAgreementsBtn'); loadAgreements(); };
    
    loadAgentProperties(); // Default view
}

function loadCustomerPanel() {
    sidebar.innerHTML = `
        <li><button id="browsePropertiesBtn">🔍 Browse Homes</button></li>
        <li><button id="myRequestsBtn">📩 My Requests</button></li>
        <li><button id="viewAgreementsBtn">📄 My Agreements</button></li>
    `;

    document.getElementById('browsePropertiesBtn').onclick = () => { setActive('browsePropertiesBtn'); loadPropertiesForCustomer(); };
    document.getElementById('myRequestsBtn').onclick = () => { setActive('myRequestsBtn'); loadRequests(); };
    document.getElementById('viewAgreementsBtn').onclick = () => { setActive('viewAgreementsBtn'); loadAgreements(); };
    
    loadPropertiesForCustomer(); // Default view
}

// Core Functionality

// Customer browse for properties available

async function loadPropertiesForCustomer() {
    dashboardContent.innerHTML = `
        <h2>Available Properties</h2>
        <div class="card-grid" id="propertyGrid">Loading...</div>
    `;

    const grid = document.getElementById('propertyGrid');

    try {
        const res = await fetch('/pata-nyumba/backend/properties.php');
        const properties = await res.json();

        grid.innerHTML = '';

        properties.forEach(p => {
            const card = document.createElement('div');
            card.className = 'card property-card';
            
            const imgPath = p.image_path ? `/pata-nyumba/${p.image_path}` : 'css/no-image.png';

            // --- Logic: Determine which button to show ---
            let actionButton = '';
            if (p.property_type === 'rent') {
                actionButton = `<button class="btn-rent" onclick="submitRequest(${p.id}, 'rent')">Request to Rent</button>`;
            } else if (p.property_type === 'sale') {
                actionButton = `<button class="btn-buy" onclick="submitRequest(${p.id}, 'purchase')">Request to Buy</button>`;
            }

            card.innerHTML = `
                <img src="${imgPath}" style="width:100%; height:180px; object-fit:cover; border-radius:8px;">
                <div class="card-content">
                    <h3>${p.title}</h3>
                    <p>📍 ${p.location}</p>
                    <p class="price"><b>KES ${Number(p.price).toLocaleString()}</b></p>
                    <p class="badge">${p.property_type.toUpperCase()}</p>
                    <div class="actions">
                        ${actionButton}
                    </div>
                </div>
            `;

            grid.appendChild(card);
        });

    } catch (err) {
        grid.innerHTML = `<p>Error loading properties.</p>`;
    }
}

// Submit request
async function submitRequest(propertyId, type) {
    const res = await fetch('/pata-nyumba/backend/requests.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ property_id: propertyId, request_type: type, message: 'I am interested in this property.' })
    });
    const data = await res.json();
    alert(data.message || data.error);
}

// Agent upload Property with its images

function showPropertyForm() {
    dashboardContent.innerHTML = `
        <h2>Upload New Property</h2>
        <form id="propertyForm" class="styled-form">
            <input type="text" id="pTitle" placeholder="Property Title (e.g. 2BR Apartment)" required>
            <textarea id="pDesc" placeholder="Full Description"></textarea>
            <input type="number" id="pPrice" placeholder="Price (KES)" required>
            <select id="pType" required>
                <option value="rent">To Rent</option>
                <option value="sale">For Sale</option>
            </select>
            <input type="text" id="pLoc" placeholder="Location (e.g. Kilimani)" required>
            <label>Upload Images (Multiple Allowed):</label>
            <input type="file" id="pImages" multiple required accept="image/*">
            <button type="submit">Submit for Approval</button>
        </form>
    `;

    document.getElementById('propertyForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('title', document.getElementById('pTitle').value);
        formData.append('description', document.getElementById('pDesc').value);
        formData.append('price', document.getElementById('pPrice').value);
        formData.append('property_type', document.getElementById('pType').value);
        formData.append('location', document.getElementById('pLoc').value);

        const imageInput = document.getElementById('pImages');
        for (let file of imageInput.files) {
            formData.append('images[]', file);
        }

        const res = await fetch('/pata-nyumba/backend/properties.php', { method: 'POST', body: formData });
        const data = await res.json();
        alert(data.message || data.error);
        if(res.ok) loadAgentProperties();
    };
}

// Agent view their own listings

async function loadAgentProperties() {
    dashboardContent.innerHTML = `<h2>My Property Listings</h2><div class="card-grid" id="agentPropGrid"></div>`;
    const res = await fetch('/pata-nyumba/backend/properties.php');
    const properties = await res.json();
    const grid = document.getElementById('agentPropGrid');

    properties.forEach(p => {
        const card = document.createElement('div');
        card.className = 'card';
        const imgPath = p.image_path ? `/pata-nyumba/${p.image_path}` : 'css/no-image.png';
        card.innerHTML = `
            <img src="${imgPath}" style="width:100%; height:120px; object-fit:cover; border-radius:5px;">
            <h3>${p.title}</h3>
            <p>Status: <b>${p.status}</b></p>
            <p>Approved: ${p.is_approved ? '✅' : '⏳'}</p>
        `;
        grid.appendChild(card);
    });
}

// view requests

async function loadRequests() {
    dashboardContent.innerHTML = `<h2>Requests Management</h2><div class="card-grid" id="requestGrid"></div>`;
    const res = await fetch('/pata-nyumba/backend/requests.php');
    const requests = await res.json();
    const grid = document.getElementById('requestGrid');

    requests.forEach(r => {
        const card = document.createElement('div');
        card.className = 'card';
        let actionHTML = '';

        if ((userRole === 'admin' || userRole === 'agent') && r.status === 'pending') {
            actionHTML = `
                <button onclick="updateRequest(${r.id}, 'approved')">Approve</button>
                <button class="danger" onclick="updateRequest(${r.id}, 'rejected')">Reject</button>
            `;
        }

        card.innerHTML = `
            <h3>${r.title}</h3>
            <p>Customer: ${r.customer_name || 'You'}</p>
            <p>Type: ${r.request_type.toUpperCase()}</p>
            <p>Status: <span class="status-${r.status}">${r.status}</span></p>
            <div class="card-actions">${actionHTML}</div>
        `;
        grid.appendChild(card);
    });
}

async function updateRequest(id, status) {
    if(!confirm(`Are you sure you want to set this request to ${status}?`)) return;
    const res = await fetch('/pata-nyumba/backend/requests.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
    });
    const data = await res.json();
    alert(data.message || data.error);
    loadRequests();
}

// view and download agreement

async function loadAgreements() {
    dashboardContent.innerHTML = `<h2>Legal Agreements</h2><div class="card-grid" id="agreementGrid"></div>`;
    const res = await fetch('/pata-nyumba/backend/agreements.php');
    const agreements = await res.json();
    const grid = document.getElementById('agreementGrid');

    agreements.forEach(a => {
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `
            <h3>${a.title || 'Agreement'}</h3>
            <p>Owner/Tenant: ${a.customer_name || userName}</p>
            <p>Date: ${new Date(a.generated_at).toLocaleDateString()}</p>
            <button onclick="viewAgreement('${a.uuid}')">Download PDF</button>
        `;
        grid.appendChild(card);
    });
}

function viewAgreement(uuid) {
    // Open secure gateway via UUID
    window.open(`/pata-nyumba/backend/agreements.php?download=${uuid}`, '_blank');
}


// Admin Management Dashboard
async function loadAllUsers() {
    dashboardContent.innerHTML = `<h2>System Users</h2><div class="card-grid" id="userGrid"></div>`;
    const res = await fetch('/pata-nyumba/backend/admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_all_users' })
    });
    const users = await res.json();
    const grid = document.getElementById('userGrid');

    users.forEach(u => {
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `
            <h3>${u.full_name}</h3>
            <p>Role: <b>${u.role}</b></p>
            <p>Email: ${u.email}</p>
            <p>Status: ${u.is_approved ? '✅ Active' : '⏳ Pending'}</p>
        `;
        grid.appendChild(card);
    });
}

async function loadApproveAgents() {
    dashboardContent.innerHTML = `<h2>Pending Agent Approvals</h2><div class="card-grid" id="agentGrid"></div>`;
    const res = await fetch('/pata-nyumba/backend/admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_pending_agents' })
    });
    const agents = await res.json();
    const grid = document.getElementById('agentGrid');

    agents.forEach(a => {
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `<h3>${a.full_name}</h3><p>${a.email}</p><button onclick="approveAction('approve_agent', ${a.id})">Approve Agent</button>`;
        grid.appendChild(card);
    });
}

async function loadApproveProperties() {
    dashboardContent.innerHTML = `<h2>Properties Awaiting Approval</h2><div class="card-grid" id="pGrid"></div>`;
    const res = await fetch('/pata-nyumba/backend/admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_pending_properties' })
    });
    const props = await res.json();
    const grid = document.getElementById('pGrid');

    props.forEach(p => {
        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `
            <img src="/pata-nyumba/${p.image_path}" style="width:100%; height:100px; object-fit:cover;">
            <h3>${p.title}</h3>
            <p>Agent: ${p.agent_name}</p>
            <button onclick="approveAction('approve_property', ${p.id})">Approve Property</button>
        `;
        grid.appendChild(card);
    });
}

async function approveAction(action, id) {
    const body = { action: action };
    if (action === 'approve_agent') body.agent_id = id;
    else body.property_id = id;

    await fetch('/pata-nyumba/backend/admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    
    if (action === 'approve_agent') loadApproveAgents();
    else loadApproveProperties();
}

// Roled based loading of Panels

switch (userRole) {
    case 'admin': loadAdminPanel(); break;
    case 'agent': loadAgentPanel(); break;
    case 'customer': loadCustomerPanel(); break;
    default: window.location.href = 'login.html';
}
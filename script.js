
const USERS_KEY = "campusBitesUsers";
const SESSION_KEY = "campusBitesSession";
const ROLE_KEY = "campusBitesRole";

const ADMIN_EMAIL = "admin@campusbites.test";
const ADMIN_PASSWORD = "admin123";

const loginArea = document.getElementById("loginArea");
const registerArea = document.getElementById("registerArea");
const adminArea = document.getElementById("adminArea");
const accountArea = document.getElementById("accountArea");

function getUsers() {
    try {
        const users = JSON.parse(localStorage.getItem(USERS_KEY) || "[]");
        return Array.isArray(users) ? users : [];
    } catch {
        return [];
    }
}

function saveUsers(users) {
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
}

function showPage(page) {
    loginArea.classList.toggle("hidden", page !== "login");
    registerArea.classList.toggle("hidden", page !== "register");
    adminArea.classList.toggle("hidden", page !== "admin");
    accountArea.classList.toggle("hidden", page !== "account");

    document.querySelectorAll(".message").forEach(message => {
        message.textContent = "";
        message.className = "message";
    });
}

document.getElementById("showRegister").onclick = () => showPage("register");
document.getElementById("showAdmin").onclick = () => showPage("admin");
document.getElementById("backFromRegister").onclick = () => showPage("login");
document.getElementById("backFromAdmin").onclick = () => showPage("login");

function showMessage(id, text, type) {
    const element = document.getElementById(id);
    element.textContent = text;
    element.className = "message " + type;
}

document.getElementById("registerForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const name = document.getElementById("registerName").value.trim();
    const email = document.getElementById("registerEmail").value.trim().toLowerCase();
    const password = document.getElementById("registerPassword").value;
    const confirmPassword = document.getElementById("confirmPassword").value;

    if (password.length < 6) {
        showMessage("registerMessage", "Password must be at least 6 characters.", "error");
        return;
    }

    if (password !== confirmPassword) {
        showMessage("registerMessage", "Passwords do not match.", "error");
        return;
    }

    const users = getUsers();

    if (users.some(user => user.email.toLowerCase() === email)) {
        showMessage("registerMessage", "This email is already registered.", "error");
        return;
    }

    users.push({ name, email, password, role: "student" });
    saveUsers(users);

    document.getElementById("registerForm").reset();
    showPage("login");
    document.getElementById("loginEmail").value = email;

    showMessage("loginMessage", "Account created! You can now log in.", "success");
});

document.getElementById("loginForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const email = document.getElementById("loginEmail").value.trim().toLowerCase();
    const password = document.getElementById("loginPassword").value;

    const user = getUsers().find(user =>
        user.email.toLowerCase() === email && user.password === password
    );

    if (!user) {
        showMessage("loginMessage", "Incorrect email or password.", "error");
        return;
    }

    sessionStorage.setItem(SESSION_KEY, user.email);
    sessionStorage.setItem(ROLE_KEY, "student");

    document.getElementById("accountName").textContent = user.name;
    document.getElementById("accountEmail").textContent = user.email;
    document.getElementById("accountRole").textContent = "Student Account";

    showPage("account");
});

document.getElementById("adminForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const email = document.getElementById("adminEmail").value.trim().toLowerCase();
    const password = document.getElementById("adminPassword").value;

    if (email !== ADMIN_EMAIL || password !== ADMIN_PASSWORD) {
        showMessage("adminMessage", "Invalid admin email or password.", "error");
        return;
    }

    sessionStorage.setItem(SESSION_KEY, ADMIN_EMAIL);
    sessionStorage.setItem(ROLE_KEY, "admin");

    document.getElementById("accountName").textContent = "Campus Bites Administrator";
    document.getElementById("accountEmail").textContent = ADMIN_EMAIL;
    document.getElementById("accountRole").textContent = "Administrator";

    window.location.href = "admin%20dashboard.html";
});

document.getElementById("logoutButton").addEventListener("click", function() {
    sessionStorage.removeItem(SESSION_KEY);
    sessionStorage.removeItem(ROLE_KEY);
    showPage("login");
    showMessage("loginMessage", "You have logged out.", "success");
});

function restoreSession() {
    const email = sessionStorage.getItem(SESSION_KEY);
    const role = sessionStorage.getItem(ROLE_KEY);

    if (!email) {
        showPage("login");
        return;
    }

    if (role === "admin" && email === ADMIN_EMAIL) {
        window.location.href = "admin%20dashboard.html";
        return;
    }

    if (role === "student") {
        const user = getUsers().find(user =>
            user.email.toLowerCase() === email.toLowerCase()
        );

        if (user) {
            document.getElementById("accountName").textContent = user.name;
            document.getElementById("accountEmail").textContent = user.email;
            document.getElementById("accountRole").textContent = "Student Account";
            showPage("account");
            return;
        }
    }

    sessionStorage.removeItem(SESSION_KEY);
    sessionStorage.removeItem(ROLE_KEY);
    showPage("login");
}

restoreSession();

async function loadProducts() {
    try {
        const response = await fetch("products.php");

        if (!response.ok) {
            throw new Error("Unable to retrieve products.");
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || "Product loading failed.");
        }

        console.log("Products from MySQL:", data.products);

        return data.products;
    } catch (error) {
        console.error(error);
        alert("Unable to load products. Please try again.");
        return [];
    }
}

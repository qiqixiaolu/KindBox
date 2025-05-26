// Sample data for recommended items
const recommendedItems = [
    {
        name: "Buku Cerita & Pendidikan",
        image: "https://via.placeholder.com/300x180?text=Buku+Cerita",
        interest: 9,
        stock: 1,
        location: "Klojen, Jawa Timur",
        isHot: true
    },
    {
        name: "Buku Cerita & Pendidikan",
        image: "https://via.placeholder.com/300x180?text=Buku+Cerita",
        interest: 9,
        stock: 1,
        location: "Klojen, Jawa Timur",
        isHot: true
    },
    {
        name: "Buku Cerita & Pendidikan",
        image: "https://via.placeholder.com/300x180?text=Buku+Cerita",
        interest: 9,
        stock: 1,
        location: "Klojen, Jawa Timur",
        isHot: true
    },
    {
        name: "Buku Cerita & Pendidikan",
        image: "https://via.placeholder.com/300x180?text=Buku+Cerita",
        interest: 9,
        stock: 1,
        location: "Klojen, Jawa Timur",
        isHot: true
    },
    {
        name: "Buku Cerita & Pendidikan",
        image: "https://via.placeholder.com/300x180?text=Buku+Cerita",
        interest: 9,
        stock: 1,
        location: "Klojen, Jawa Timur",
        isHot: true
    },
    {
        name: "Buku Cerita & Pendidikan",
        image: "https://via.placeholder.com/300x180?text=Buku+Cerita",
        interest: 9,
        stock: 1,
        location: "Klojen, Jawa Timur",
        isHot: true
    },
    {
        name: "Buku Cerita & Pendidikan",
        image: "https://via.placeholder.com/300x180?text=Buku+Cerita",
        interest: 9,
        stock: 1,
        location: "Klojen, Jawa Timur",
        isHot: true
    }
];

// Function to create item card HTML
function createItemCard(item) {
    return `
        <div class="item-card">
            <img src="${item.image}" alt="${item.name}" class="item-image">
            <div class="item-details">
                <div class="item-name">${item.name}</div>
                <div class="item-info">
                    <i class="fas fa-heart ${item.isHot ? 'hot-item' : ''}"></i>
                    <span class="${item.isHot ? 'hot-item' : ''}">${item.interest} Peminat. Sisa ${item.stock} Barang</span>
                </div>
                <div class="item-info">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>${item.location}</span>
                </div>
                <a href="#" class="view-detail">Lihat Detail</a>
            </div>
        </div>
    `;
}

// Populate recommended items
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainer = document.querySelector('.items-container');
    
    recommendedItems.forEach(item => {
        itemsContainer.innerHTML += createItemCard(item);
    });
    
    // Add event listeners
    document.querySelectorAll('.filter-btn, .desktop-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            alert('Fitur filter akan menampilkan opsi: Barang terdekat, Paling banyak diminati, Paling banyak stok, Kategori barang, Barang baru');
        });
    });
    
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function() {
            const text = this.textContent;
            if (text === 'Home') {
                // Already on home
            } else if (text === 'Upload Barang') {
                alert('Navigasi ke halaman Upload Barang');
            }
        });
    });
    
    document.querySelector('.profile-icon').addEventListener('click', function() {
        alert('Navigasi ke halaman Profil');
    });
    
    document.querySelectorAll('.view-detail').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Navigasi ke halaman detail barang');
        });
    });
});
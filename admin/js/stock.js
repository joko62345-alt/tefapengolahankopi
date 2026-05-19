
        // Hamburger Menu Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        hamburgerBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) toggleSidebar();
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        //  Open Print Report
        function openPrintReport() {
            const bean = document.getElementById('filter_bean')?.value || '';
            const jenis = document.getElementById('filter_jenis')?.value || '';
            const dateFrom = document.getElementById('filter_date_from')?.value || '';
            const dateTo = document.getElementById('filter_date_to')?.value || '';
            const params = new URLSearchParams();
            params.append('print', '1');
            if (bean) params.append('filter_bean', bean);
            if (jenis) params.append('filter_jenis', jenis);
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);
            window.location.href = 'stock.php?' + params.toString();
        }

        //  Apply Filter
        function applyFilter() {
            const bean = document.getElementById('filter_bean').value;
            const jenis = document.getElementById('filter_jenis').value;
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            updateActiveFilters(bean, jenis, dateFrom, dateTo);
            const params = new URLSearchParams();
            if (bean) params.append('filter_bean', bean);
            if (jenis) params.append('filter_jenis', jenis);
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);
            window.location.href = 'stock.php?' + params.toString();
        }

        // Reset Filters
        function resetFilters() {
            window.location.href = 'stock.php';
        }

        // Update Active Filters Indicator
        function updateActiveFilters(bean, jenis, dateFrom, dateTo) {
            const indicator = document.getElementById('activeFilters');
            const text = document.getElementById('activeFiltersText');
            if (!indicator || !text) return;
            const filters = [];
            if (bean) {
                const select = document.getElementById('filter_bean');
                const name = select.options[select.selectedIndex].text;
                filters.push(`Biji: ${name}`);
            }
            if (jenis) filters.push(`Jenis: ${jenis.charAt(0).toUpperCase() + jenis.slice(1)}`);
            if (dateFrom) filters.push(`Dari: ${dateFrom}`);
            if (dateTo) filters.push(`Sampai: ${dateTo}`);
            if (filters.length > 0) {
                text.textContent = filters.join(' • ');
                indicator.classList.add('show');
            } else {
                indicator.classList.remove('show');
            }
        }

        //  Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            const bean = document.getElementById('filter_bean')?.value;
            const jenis = document.getElementById('filter_jenis')?.value;
            const dateFrom = document.getElementById('filter_date_from')?.value;
            const dateTo = document.getElementById('filter_date_to')?.value;
            if (bean || jenis || dateFrom || dateTo) {
                updateActiveFilters(bean, jenis, dateFrom, dateTo);
            }
        });
   
        // Validasi stok keluar tidak melebihi stok tersedia
function validateStockOut() {
    const jenis = document.querySelector('select[name="jenis"]').value;
    const jumlahInput = document.querySelector('input[name="jumlah"]');
    const jumlah = parseFloat(jumlahInput.value) || 0;
    const namaBiji = document.querySelector('input[name="nama_biji_kopi"]').value.trim();
    
    if (jenis === 'keluar' && jumlah > 0 && namaBiji) {
        // Fetch stok tersedia via AJAX
        fetch(`api/get_stock.php?nama_biji_kopi=${encodeURIComponent(namaBiji)}`)
            .then(res => res.json())
            .then(data => {
                const availableStock = parseFloat(data.stok) || 0;
                
                if (jumlah > availableStock) {
                    alert(` Stok tidak mencukupi!\n\n` +
                          `Biji Kopi: ${namaBiji}\n` +
                          `Stok tersedia: ${availableStock.toFixed(1)} kg\n` +
                          `Anda mencoba mengurangi: ${jumlah.toFixed(1)} kg\n\n` +
                          `Silakan kurangi jumlah atau lakukan restok terlebih dahulu.`);
                    jumlahInput.value = availableStock.toFixed(1);
                    jumlahInput.focus();
                    return false;
                }
            })
            .catch(err => console.error('Error checking stock:', err));
    }
    return true;
}

// Attach validation to form submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const jenis = document.querySelector('select[name="jenis"]').value;
            if (jenis === 'keluar') {
                // Untuk validasi instan tanpa AJAX, kita bisa tampilkan warning saja
                const jumlah = parseFloat(document.querySelector('input[name="jumlah"]').value) || 0;
                if (jumlah > 1000) { // Threshold warning (opsional)
                    if (!confirm(` Anda akan mengurangi stok sebanyak ${jumlah.toFixed(1)} kg.\n\nYakin ingin melanjutkan?`)) {
                        e.preventDefault();
                    }
                }
            }
        });
    }
    
    // Auto-hide alert after 5 seconds
    const alert = document.querySelector('.alert-success, .alert-danger');
    if (alert) {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }
});
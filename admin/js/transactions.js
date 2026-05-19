
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
            link.addEventListener('click', () => { if (window.innerWidth <= 768) toggleSidebar(); });
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // View Detail Function
        function viewDetail(id) {
            const modalContent = document.getElementById('detailContent');
            modalContent.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><p>Memuat detail transaksi...</p></div>';
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
            fetch('ajax_transaction_detail.php?id=' + id)
                .then(response => response.json())
                .then(data => { modalContent.innerHTML = data.html; })
                .catch(error => { modalContent.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Gagal memuat detail transaksi.</div>'; });
        }

        // View Receipt - SAMA PERSIS DENGAN DASHBOARD.PHP
        function viewReceipt(kode) {
            fetch(`receipt.php?kode=${encodeURIComponent(kode)}&format=json`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.error) { alert(data.error); return; }
                    populateReceiptModal(data);
                    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
                    modal.show();
                })
                .catch(error => { console.error('Error:', error); alert('Gagal memuat struk'); });
        }

        // Populate Receipt Modal
        function populateReceiptModal(data) {
            document.getElementById('receiptKode').textContent = data.kode_transaksi;
            document.getElementById('receiptTanggal').textContent = formatDateIndonesia(data.tanggal_transaksi);
            document.getElementById('receiptNama').textContent = data.nama_lengkap;
            document.getElementById('receiptTelepon').textContent = data.telepon;

            const statusEl = document.getElementById('receiptStatus');
            statusEl.textContent = data.status_pembayaran.toUpperCase();
            statusEl.style.background = data.status_pembayaran === 'lunas' ? '#000' : '#fff';
            statusEl.style.color = data.status_pembayaran === 'lunas' ? '#fff' : '#000';

            const pickupStatusEl = document.getElementById('receiptPickupStatus');
            const pickupInfoEl = document.getElementById('receiptPickupInfo');
            if (data.status_pengambilan === 'sudah_diambil') {
                pickupStatusEl.innerHTML = '✓ SUDAH DIAMBIL';
                pickupInfoEl.innerHTML = formatDateIndonesia(data.tanggal_diambil) + '<br>Oleh: ' + data.diambil_oleh;
            } else {
                pickupStatusEl.innerHTML = ' BELUM DIAMBIL';
                pickupInfoEl.innerHTML = 'Tunjukkan struk ini ke admin saat ambil';
            }

            let itemsHtml = '';
            data.items.forEach(item => {
                const qty = item.qty || item.quantity || 1;
                itemsHtml += `<div class="receipt-item"><span class="item-name">${item.nama_produk}</span><span class="item-qty">${qty}x</span><span class="item-price">${formatRupiah(item.subtotal)}</span></div>`;
            });
            document.getElementById('receiptItems').innerHTML = itemsHtml;
            document.getElementById('receiptTotal').textContent = 'Rp ' + formatRupiah(data.total_harga);
            document.getElementById('receiptTimestamp').textContent = new Date().toLocaleString('id-ID');
        }

        function formatDateIndonesia(dateString) {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        }

        function formatRupiah(angka) {
            return parseInt(angka).toLocaleString('id-ID');
        }

        // Print Modal Receipt
        function printModalReceipt() {
            const printWindow = window.open('', '_blank', 'width=400,height=700');
            const modalContent = document.querySelector('#receiptModal .modal-content');
            printWindow.document.write(`<!DOCTYPE html><html><head><title>Cetak Struk</title><style>body { font-family: 'Courier New', Courier, monospace; padding: 20px; background: white; margin: 0; } .modal-content { box-shadow: none !important; border: none !important; max-width: 100%; } .modal-footer, .btn-close { display: none !important; } @media print { body { padding: 0; } .no-print { display: none !important; } }</style></head><body>${modalContent.outerHTML}</body></html>`);
            printWindow.document.close();
            setTimeout(() => printWindow.print(), 500);
        }

        // Open Print Report
        function openPrintReport() {
            const dateFrom = document.getElementById('filter_date_from')?.value || '';
            const dateTo = document.getElementById('filter_date_to')?.value || '';
            const pembayaran = document.getElementById('filter_pembayaran')?.value || '';
            const pengambilan = document.getElementById('filter_pengambilan')?.value || '';
            const customer = document.getElementById('filter_customer')?.value || '';
            const params = new URLSearchParams();
            params.append('print', '1');
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);
            if (pembayaran) params.append('filter_pembayaran', pembayaran);
            if (pengambilan) params.append('filter_pengambilan', pengambilan);
            if (customer) params.append('filter_customer', customer);
            window.location.href = 'transactions.php?' + params.toString();
        }

        // Apply Filter
        function applyFilter() {
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            const pembayaran = document.getElementById('filter_pembayaran').value;
            const pengambilan = document.getElementById('filter_pengambilan').value;
            const customer = document.getElementById('filter_customer').value;
            updateActiveFilters(dateFrom, dateTo, pembayaran, pengambilan, customer);
            const params = new URLSearchParams();
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);
            if (pembayaran) params.append('filter_pembayaran', pembayaran);
            if (pengambilan) params.append('filter_pengambilan', pengambilan);
            if (customer) params.append('filter_customer', customer);
            window.location.href = 'transactions.php?' + params.toString();
        }

        // Reset Filters
        function resetFilters() {
            window.location.href = 'transactions.php';
        }

        // Update Active Filters Indicator
        function updateActiveFilters(dateFrom, dateTo, pembayaran, pengambilan, customer) {
            const indicator = document.getElementById('activeFilters');
            const text = document.getElementById('activeFiltersText');
            if (!indicator || !text) return;
            const filters = [];
            if (dateFrom) filters.push(`Dari: ${dateFrom}`);
            if (dateTo) filters.push(`Sampai: ${dateTo}`);
            if (pembayaran) filters.push(`Pembayaran: ${pembayaran.toUpperCase()}`);
            if (pengambilan) filters.push(`Pengambilan: ${pengambilan.replace('_', ' ')}`);
            if (customer) filters.push(`Customer: ${customer}`);
            if (filters.length > 0) {
                text.textContent = filters.join(' • ');
                indicator.classList.add('show');
            } else {
                indicator.classList.remove('show');
            }
        }

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            const dateFrom = document.getElementById('filter_date_from')?.value;
            const dateTo = document.getElementById('filter_date_to')?.value;
            const pembayaran = document.getElementById('filter_pembayaran')?.value;
            const pengambilan = document.getElementById('filter_pengambilan')?.value;
            const customer = document.getElementById('filter_customer')?.value;
            if (dateFrom || dateTo || pembayaran || pengambilan || customer) {
                updateActiveFilters(dateFrom, dateTo, pembayaran, pengambilan, customer);
            }
        });
   //  Fungsi Cetak ke Thermal Printer
function printModalReceipt() {
    // Tambahkan class print-mode ke body untuk trigger CSS @media print
    document.body.classList.add('printing');
    
    // Trigger print dialog
    window.print();
    
    // Opsional: Tutup modal setelah print (dengan delay agar print selesai)
    setTimeout(function() {
        document.body.classList.remove('printing');
        // Jika ingin auto-close modal setelah print:
        // const modal = bootstrap.Modal.getInstance(document.getElementById('receiptModal'));
        // if (modal) modal.hide();
    }, 1000);
}

//  Alternative: Direct print tanpa dialog (hanya untuk browser yang support)
function printModalReceiptDirect() {
    const printWindow = window.open('', '_blank', 'width=320,height=600');
    const receiptContent = document.querySelector('.receipt-modal-body').innerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cetak Struk</title>
            <style>
                @page { size: 80mm auto; margin: 0; }
                body { 
                    font-family: 'Courier New', monospace; 
                    font-size: 10px; 
                    margin: 0; 
                    padding: 5px;
                    width: 80mm;
                }
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            </style>
        </head>
        <body onload="window.print(); setTimeout(() => window.close(), 1000);">
            ${receiptContent}
        </body>
        </html>
    `);
    printWindow.document.close();
}
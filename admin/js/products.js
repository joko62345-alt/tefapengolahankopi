
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

        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById(previewId);
                    if (preview) {
                        preview.src = e.target.result;
                        preview.classList.add('show');
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
            function editProduct(id, nama, deskripsi, harga, stok, kategori, gambar) {
    try {
        console.log('🔧 editProduct called:', { id, nama, stok }); // Debug log
        
        // Helper: aman set value jika elemen ada
        const setValue = (elementId, value) => {
            const el = document.getElementById(elementId);
            if (el) el.value = value ?? '';
            else console.warn(`⚠️ Element #${elementId} not found`);
        };

        const setText = (elementId, text) => {
            const el = document.getElementById(elementId);
            if (el) el.textContent = text ?? '0';
        };

        const setStyle = (elementId, style, value) => {
            const el = document.getElementById(elementId);
            if (el) el.style[style] = value;
        };

        // Isi field form
        setValue('edit_id', id);
        setValue('edit_nama', nama);
        setValue('edit_deskripsi', deskripsi);
        setValue('edit_harga', harga);
        setValue('edit_stok_display', stok);
        setValue('edit_kategori', kategori); // ✅ input text, bukan select
        setValue('edit_gambar_lama', gambar);
        
        // Reset stok adjustments
        setValue('edit_tambah_stok', '');
        setValue('edit_kurangi_stok', '');
        setValue('edit_stock_keterangan', '');
        setText('new_stock_value', stok);

        // Handle gambar
        const currentImg = document.getElementById('edit_current_img');
        const noImg = document.getElementById('edit_no_img');
        
        if (currentImg && noImg) {
            if (gambar && gambar !== '') {
                currentImg.src = '../assets/images/products/' + gambar;
                currentImg.style.display = 'block';
                noImg.style.display = 'none';
            } else {
                currentImg.style.display = 'none';
                noImg.style.display = 'flex';
            }
        }

        // Clear preview baru
        const previewEdit = document.getElementById('previewEdit');
        if (previewEdit) previewEdit.src = '';

        // ✅ Pastikan Bootstrap sudah load sebelum bikin modal
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            console.error('❌ Bootstrap.Modal not ready yet!');
            // Fallback: reload halaman dengan hash untuk buka modal
            window.location.hash = `#edit-${id}`;
            return;
        }

        const modalEl = document.getElementById('editModal');
        if (!modalEl) {
            console.error('❌ Modal element #editModal not found!');
            return;
        }

        // Cek apakah modal sudah di-inisialisasi sebelumnya
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.show();
        
        console.log(' Modal shown successfully');
        
    } catch (e) {
        console.error(' Error in editProduct():', e);
        alert('Gagal membuka form edit. Silakan refresh halaman.');
    }
}
        // Tutup fungsi di sini, jangan sebelumnya!

        function calculateNewStock() {
            const currentStock = parseInt(document.getElementById('edit_stok_display').value) || 0;
            const tambahStock = parseInt(document.getElementById('edit_tambah_stok').value) || 0;
            const kurangiStock = parseInt(document.getElementById('edit_kurangi_stok').value) || 0;
            const newStock = currentStock + tambahStock - kurangiStock;
            const newStockEl = document.getElementById('new_stock_value');
            if (newStockEl) {
                newStockEl.textContent = newStock;
                if (newStock < currentStock) {
                    newStockEl.className = 'fw-bold text-danger';
                } else if (newStock > currentStock) {
                    newStockEl.className = 'fw-bold text-success';
                } else {
                    newStockEl.className = 'fw-bold';
                }
            }
        }

        function openPrintReport() {
            const product = document.getElementById('filter_product').value;
            const jenis = document.getElementById('filter_jenis').value;
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            const params = new URLSearchParams();
            params.append('print', '1');
            if (product) params.append('filter_product', product);
            if (jenis) params.append('filter_jenis', jenis);
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);
            window.location.href = 'products.php?' + params.toString();
        }

        function applyFilter() {
            const container = document.getElementById('historyTableContainer');
            const filterForm = document.getElementById('filterForm');
            if (container) container.classList.add('loading');
            if (filterForm) filterForm.classList.add('filter-loading');

            const product = document.getElementById('filter_product').value;
            const jenis = document.getElementById('filter_jenis').value;
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            const params = new URLSearchParams();
            if (product) params.append('filter_product', product);
            if (jenis) params.append('filter_jenis', jenis);
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);

            fetch('ajax_filter_history.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    updateHistoryTable(data);
                    updateActiveFilters(product, jenis, dateFrom, dateTo);
                })
                .catch(error => {
                    console.error('Filter error:', error);
                    alert(' Gagal memuat data filter');
                })
                .finally(() => {
                    if (container) container.classList.remove('loading');
                    if (filterForm) filterForm.classList.remove('filter-loading');
                });
        }

        function updateHistoryTable(data) {
            const tbody = document.getElementById('historyTableBody');
            const displayedCount = document.getElementById('displayedCount');
            const totalCount = document.getElementById('totalCount');
            if (!tbody) return;

            if (data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6"><div class="no-results"><i class="fas fa-search"></i><p class="mb-0">Tidak ada data yang sesuai filter</p><small>Coba ubah kriteria filter Anda</small></div></td></tr>`;
            } else {
                let html = '';
                data.slice(0, 10).forEach(hist => {
                    const jenisClass = hist.jenis === 'masuk' ? 'badge-success' : 'badge-danger';
                    const jenisIcon = hist.jenis === 'masuk' ? 'arrow-down' : 'arrow-up';
                    const textClass = hist.jenis === 'masuk' ? 'text-success' : 'text-danger';
                    const sign = hist.jenis === 'masuk' ? '+' : '-';
                    html += `<tr>
                <td>${formatDate(hist.tanggal)}</td>
                <td class="fw-semibold">${escapeHtml(hist.nama_produk)}</td>
                <td>${capitalize(hist.kategori)}</td>
                <td><span class="badge history-badge ${jenisClass}"><i class="fas fa-${jenisIcon}"></i> ${capitalize(hist.jenis)}</span></td>
                <td class="fw-bold ${textClass}">${sign}${hist.jumlah}</td>
                <td>${escapeHtml(hist.keterangan || '-')}</td>
            </tr>`;
                });
                tbody.innerHTML = html;
            }
            if (displayedCount) displayedCount.textContent = Math.min(data.length, 10);
            if (totalCount) totalCount.textContent = data.length;
        }

        function updateActiveFilters(product, jenis, dateFrom, dateTo) {
            const indicator = document.getElementById('activeFilters');
            const text = document.getElementById('activeFiltersText');
            if (!indicator || !text) return;

            const filters = [];
            if (product) {
                const productSelect = document.getElementById('filter_product');
                const productName = productSelect.options[productSelect.selectedIndex].text;
                filters.push(`Produk: ${productName}`);
            }
            if (jenis) filters.push(`Jenis: ${capitalize(jenis)}`);
            if (dateFrom) filters.push(`Dari: ${dateFrom}`);
            if (dateTo) filters.push(`Sampai: ${dateTo}`);

            if (filters.length > 0) {
                text.textContent = filters.join(' • ');
                indicator.classList.add('show');
            } else {
                indicator.classList.remove('show');
            }
        }

        function resetFilters() {
            const filterProduct = document.getElementById('filter_product');
            const filterJenis = document.getElementById('filter_jenis');
            const filterDateFrom = document.getElementById('filter_date_from');
            const filterDateTo = document.getElementById('filter_date_to');
            const activeFilters = document.getElementById('activeFilters');
            if (filterProduct) filterProduct.value = '';
            if (filterJenis) filterJenis.value = '';
            if (filterDateFrom) filterDateFrom.value = '';
            if (filterDateTo) filterDateTo.value = '';
            if (activeFilters) activeFilters.classList.remove('show');
            window.location.href = 'products.php';
        }

        function openHistoryModal() {
            const historyModal = document.getElementById('historyModal');
            if (historyModal) {
                const modal = new bootstrap.Modal(historyModal);
                modal.show();
            }
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }, 5000);
            });

            const filterProduct = document.getElementById('filter_product');
            const filterJenis = document.getElementById('filter_jenis');
            const filterDateFrom = document.getElementById('filter_date_from');
            const filterDateTo = document.getElementById('filter_date_to');
            if (filterProduct || filterJenis || filterDateFrom || filterDateTo) {
                updateActiveFilters(filterProduct?.value, filterJenis?.value, filterDateFrom?.value, filterDateTo?.value);
            }
        });
    
        
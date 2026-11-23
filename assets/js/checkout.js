(function (window, document) {
    'use strict';

    function initCheckout() {
        const formCheckout = document.getElementById('formCheckout');
        if (!formCheckout) return;

        const inputNama       = document.getElementById('nama_penyewa');
        const inputTelepon    = document.getElementById('no_telepon');
        const inputBukti      = document.getElementById('bukti_pembayaran');
        const cekSyarat       = document.getElementById('setuju_syarat');
        const btnSubmit       = document.getElementById('btnSubmitCheckout');

        const errNama         = document.getElementById('errNamaPenyewa');
        const errTelepon      = document.getElementById('errNoTelepon');
        const errBukti        = document.getElementById('errBuktiPembayaran');
        const errSyarat       = document.getElementById('errSetujuSyarat');

        const kotakNominal    = document.getElementById('ringkasanNominalCheckout');
        const barisDp         = document.getElementById('barisDpCheckout');
        const teksNominalAwal = document.getElementById('nominalBayarAwalTampil');

        const panelTransfer   = document.getElementById('panelTransferBank');
        const panelQris       = document.getElementById('panelQris');
        const btnSalin        = document.getElementById('btnSalinRekening');

        const previewContainer = document.getElementById('previewBuktiContainer');
        const previewGambar    = document.getElementById('previewBuktiGambar');
        const previewFile      = document.getElementById('previewBuktiFile');
        const btnHapusBukti    = document.getElementById('btnHapusBukti');

        let sedangMengirim = false;

        /**
         * Normalisasi karakter spasi/strip pada nomor telepon.
         */
        function normalisasiTelepon(teks) {
            if (!teks) return '';
            return String(teks).trim().replace(/[\s\-().]/g, '');
        }

        function setError(elPesan, elInput, pesan) {
            if (!elPesan) return;
            if (pesan) {
                elPesan.textContent = pesan;
                elPesan.classList.remove('hidden');
                if (elInput) elInput.setAttribute('aria-invalid', 'true');
            } else {
                elPesan.textContent = '';
                elPesan.classList.add('hidden');
                if (elInput) elInput.removeAttribute('aria-invalid');
            }
        }

        function validasiNama() {
            if (!inputNama) return true;
            const nama = inputNama.value.trim();
            if (nama.length < 3 || nama.length > 100) {
                setError(errNama, inputNama, 'Nama lengkap wajib diisi antara 3 sampai 100 karakter.');
                return false;
            }
            setError(errNama, inputNama, null);
            return true;
        }

        function validasiTelepon() {
            if (!inputTelepon) return true;
            const tel = normalisasiTelepon(inputTelepon.value);
            const pola = /^\+?[0-9]{8,14}$/;
            if (!pola.test(tel)) {
                setError(
                    errTelepon,
                    inputTelepon,
                    'Nomor WhatsApp/telepon harus berisi 8 sampai 14 digit angka (contoh: 081234567890).'
                );
                return false;
            }
            inputTelepon.value = tel;
            setError(errTelepon, inputTelepon, null);
            return true;
        }

        function validasiBukti() {
            if (!inputBukti) return true;
            const file = inputBukti.files && inputBukti.files[0];
            if (!file) {
                setError(errBukti, inputBukti, 'Wajib memilih dan mengunggah foto / file bukti pembayaran.');
                return false;
            }

            const maxBytes = 5 * 1024 * 1024; // 5 MB
            if (file.size > maxBytes) {
                setError(errBukti, inputBukti, 'Ukuran file melebihi 5 MB. Silakan kompres atau pilih file lain.');
                return false;
            }

            const tipeIzin = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            const namaFile = file.name.toLowerCase();
            const ekstensiValid = namaFile.endsWith('.jpg') || namaFile.endsWith('.jpeg') || namaFile.endsWith('.png') || namaFile.endsWith('.webp') || namaFile.endsWith('.pdf');

            if (!tipeIzin.includes(file.type) && !ekstensiValid) {
                setError(errBukti, inputBukti, 'Format file tidak diizinkan. Gunakan JPG, PNG, WEBP, atau PDF.');
                return false;
            }

            setError(errBukti, inputBukti, null);
            return true;
        }

        function validasiSyarat() {
            if (!cekSyarat) return true;
            if (!cekSyarat.checked) {
                setError(errSyarat, cekSyarat, 'Anda wajib menyetujui Syarat & Ketentuan penyewaan terlebih dahulu.');
                return false;
            }
            setError(errSyarat, cekSyarat, null);
            return true;
        }

        // Toggle Pratinjau File Bukti Pembayaran
        if (inputBukti) {
            inputBukti.addEventListener('change', function () {
                const ok = validasiBukti();
                const file = inputBukti.files && inputBukti.files[0];

                if (!ok || !file) {
                    if (previewContainer) previewContainer.classList.add('hidden');
                    return;
                }

                if (previewContainer && previewFile) {
                    const ukuranKb = Math.round(file.size / 1024);
                    previewFile.innerHTML = '<strong>' + escapeHtml(file.name) + '</strong> (' + ukuranKb + ' KB)';
                    previewContainer.classList.remove('hidden');
                    previewContainer.classList.add('flex');

                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            if (previewGambar) {
                                previewGambar.src = e.target.result;
                                previewGambar.classList.remove('hidden');
                            }
                        };
                        reader.readAsDataURL(file);
                    } else {
                        if (previewGambar) {
                            previewGambar.src = '';
                            previewGambar.classList.add('hidden');
                        }
                    }
                }
            });
        }

        if (btnHapusBukti && inputBukti) {
            btnHapusBukti.addEventListener('click', function () {
                inputBukti.value = '';
                if (previewContainer) previewContainer.classList.add('hidden');
                setError(errBukti, inputBukti, null);
            });
        }

        // Toggle Metode Pembayaran: Transfer Bank vs QRIS
        const radiosMetode = formCheckout.querySelectorAll('input[name="jenis_pembayaran"]');
        radiosMetode.forEach(function (radio) {
            radio.addEventListener('change', function () {
                const isTransfer = (radio.value === 'transfer' && radio.checked);
                const isQris     = (radio.value === 'qris' && radio.checked);

                radiosMetode.forEach(function (r) {
                    const label = r.closest('.pilihan-radio-kartu');
                    if (label) {
                        label.classList.toggle('pilihan-radio-kartu--aktif', r.checked);
                    }
                });

                if (panelTransfer) panelTransfer.classList.toggle('hidden', !isTransfer);
                if (panelQris) panelQris.classList.toggle('hidden', !isQris);
            });
        });

        // Tombol Salin Nomor Rekening
        if (btnSalin) {
            btnSalin.addEventListener('click', function () {
                const rek = btnSalin.dataset.salin || '8120445910';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(rek).then(function () {
                        if (window.SnapRent && window.SnapRent.toast) {
                            window.SnapRent.toast('Nomor rekening BCA (' + rek + ') berhasil disalin!', 'sukses');
                        } else {
                            alert('Nomor rekening disalin: ' + rek);
                        }
                    }).catch(function () {
                        prompt('Salin nomor rekening:', rek);
                    });
                } else {
                    prompt('Salin nomor rekening:', rek);
                }
            });
        }

        // Perbarui tampilan nominal bayar awal saat skema LUNAS / DP 50% diganti
        const radiosSkema = formCheckout.querySelectorAll('input[name="skema_pembayaran"]');
        radiosSkema.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!kotakNominal || !teksNominalAwal) return;
                const total = parseFloat(kotakNominal.dataset.total || '0') || 0;
                const dp    = parseFloat(kotakNominal.dataset.dp || '0') || 0;
                const pakaiDp = (radio.value === 'dp_50' && radio.checked);

                if (barisDp) {
                    barisDp.classList.toggle('hidden', !pakaiDp);
                }
                if (window.SnapRent && window.SnapRent.formatRupiah) {
                    teksNominalAwal.textContent = window.SnapRent.formatRupiah(pakaiDp ? dp : total);
                }
            });
        });

        if (inputNama) {
            inputNama.addEventListener('input', function () {
                if (!errNama.classList.contains('hidden')) validasiNama();
            });
        }
        if (inputTelepon) {
            inputTelepon.addEventListener('input', function () {
                if (!errTelepon.classList.contains('hidden')) validasiTelepon();
            });
        }
        if (cekSyarat) {
            cekSyarat.addEventListener('change', validasiSyarat);
        }

        formCheckout.addEventListener('submit', function (ev) {
            if (sedangMengirim) {
                ev.preventDefault();
                return;
            }

            const okNama    = validasiNama();
            const okTelepon = validasiTelepon();
            const okBukti   = validasiBukti();
            const okSyarat  = validasiSyarat();

            if (!okNama || !okTelepon || !okBukti || !okSyarat) {
                ev.preventDefault();
                if (!okNama && inputNama) {
                    inputNama.focus();
                } else if (!okTelepon && inputTelepon) {
                    inputTelepon.focus();
                } else if (!okBukti && inputBukti) {
                    inputBukti.focus();
                } else if (!okSyarat && cekSyarat) {
                    cekSyarat.focus();
                }
                return;
            }

            // Nonaktifkan tombol untuk mencegah pengiriman ganda
            sedangMengirim = true;
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML =
                    '<span class="spinner-katalog" style="border-color: rgba(255,255,255,0.3); border-top-color: #fff;" aria-hidden="true"></span>' +
                    '<span>Mengunggah Bukti &amp; Memproses Pesanan...</span>';
            }
        });

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCheckout);
    } else {
        initCheckout();
    }
})(window, document);

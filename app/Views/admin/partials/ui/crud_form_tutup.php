<?php
/**
 * Penutup dialog formulir Tambah / Ubah (pasangan ui_crud_form_buka): kaki dengan Batal + Simpan (ikon centang).
 * Panggil: ui_crud_form_tutup() atau ui_crud_form_tutup(['simpan' => 'Simpan data']).
 *
 * @var array{simpan?: string} $ui
 */
?>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutupForm()">Batal</button>
                <button type="submit" class="btn btn-utama"><?= ikon('check', 'h-4 w-4') ?><?= esc((string) ($ui['simpan'] ?? 'Simpan')) ?></button>
            </div>
        </form>
    </div>
</div>

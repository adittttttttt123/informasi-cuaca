<?php
declare(strict_types=1);

const DATA_FILE = __DIR__ . '/data/cities.json';

function ensureDataFile(): void
{
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (!file_exists(DATA_FILE)) {
        $seed = [
            [
                'id' => 1,
                'name' => 'Jakarta',
                'country' => 'Indonesia',
                'category' => 'Ibukota',
                'notes' => 'Kota utama untuk pantauan cuaca harian.',
                'created_at' => date('c'),
            ],
            [
                'id' => 2,
                'name' => 'Tokyo',
                'country' => 'Japan',
                'category' => 'Bisnis',
                'notes' => 'Kota favorit untuk pembanding cuaca Asia Timur.',
                'created_at' => date('c'),
            ],
            [
                'id' => 3,
                'name' => 'London',
                'country' => 'United Kingdom',
                'category' => 'Wisata',
                'notes' => 'Sering dipakai sebagai contoh kota dengan cuaca berubah cepat.',
                'created_at' => date('c'),
            ],
        ];
        file_put_contents(DATA_FILE, json_encode($seed, JSON_PRETTY_PRINT));
    }
}

function readCities(): array
{
    ensureDataFile();
    $json = file_get_contents(DATA_FILE);
    $data = json_decode((string) $json, true);
    return is_array($data) ? $data : [];
}

function saveCities(array $cities): void
{
    ensureDataFile();
    file_put_contents(DATA_FILE, json_encode(array_values($cities), JSON_PRETTY_PRINT));
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function findCity(array $cities, int $id): ?array
{
    foreach ($cities as $city) {
        if ((int) $city['id'] === $id) {
            return $city;
        }
    }
    return null;
}

function validateCity(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $country = trim((string) ($input['country'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    $errors = [];

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Nama kota wajib diisi minimal 2 karakter.';
    }
    if ($country === '') {
        $errors[] = 'Negara wajib diisi.';
    }
    if ($category === '') {
        $errors[] = 'Kategori wajib dipilih.';
    }

    return [$errors, compact('name', 'country', 'category', 'notes')];
}

$cities = readCities();
$page = $_GET['page'] ?? 'dashboard';
$action = $_POST['action'] ?? '';
$flash = '';
$errors = [];
$formData = ['name' => '', 'country' => '', 'category' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        [$errors, $formData] = validateCity($_POST);
        if (!$errors) {
            $nextId = $cities ? max(array_map(fn($city) => (int) $city['id'], $cities)) + 1 : 1;
            $cities[] = $formData + ['id' => $nextId, 'created_at' => date('c')];
            saveCities($cities);
            header('Location: index.php?page=list&flash=created');
            exit;
        }
        $page = 'add';
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        [$errors, $formData] = validateCity($_POST);
        if (!$errors) {
            foreach ($cities as &$city) {
                if ((int) $city['id'] === $id) {
                    $city = $formData + [
                        'id' => $id,
                        'created_at' => $city['created_at'] ?? date('c'),
                        'updated_at' => date('c'),
                    ];
                    break;
                }
            }
            unset($city);
            saveCities($cities);
            header('Location: index.php?page=list&flash=updated');
            exit;
        }
        $page = 'edit';
        $_GET['id'] = (string) $id;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $cities = array_values(array_filter($cities, fn($city) => (int) $city['id'] !== $id));
        saveCities($cities);
        header('Location: index.php?page=list&flash=deleted');
        exit;
    }
}

if (isset($_GET['flash'])) {
    $messages = [
        'created' => 'Data kota berhasil ditambahkan.',
        'updated' => 'Data kota berhasil diperbarui.',
        'deleted' => 'Data kota berhasil dihapus.',
    ];
    $flash = $messages[$_GET['flash']] ?? '';
}

$query = trim((string) ($_GET['q'] ?? ''));
$filter = trim((string) ($_GET['category'] ?? ''));
$filteredCities = array_values(array_filter($cities, function ($city) use ($query, $filter) {
    $matchesQuery = $query === ''
        || stripos($city['name'], $query) !== false
        || stripos($city['country'], $query) !== false;
    $matchesFilter = $filter === '' || $city['category'] === $filter;
    return $matchesQuery && $matchesFilter;
}));

$categories = array_values(array_unique(array_filter(array_map(fn($city) => $city['category'] ?? '', $cities))));
sort($categories);
$selectedCity = isset($_GET['id']) ? findCity($cities, (int) $_GET['id']) : null;
if (($page === 'edit' || $page === 'detail') && !$selectedCity && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $page = 'list';
    $flash = 'Data kota tidak ditemukan.';
}
if ($page === 'edit' && $selectedCity && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $formData = [
        'name' => $selectedCity['name'],
        'country' => $selectedCity['country'],
        'category' => $selectedCity['category'],
        'notes' => $selectedCity['notes'] ?? '',
    ];
}

$totalCities = count($cities);
$totalCountries = count(array_unique(array_map(fn($city) => strtolower($city['country']), $cities)));
$mainCity = $cities[0] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Cuaca Kota</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <span class="brand-mark">C</span>
            <span>CuacaKota</span>
        </a>
        <nav class="nav-menu" aria-label="Navigasi utama">
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php"><i data-lucide="layout-dashboard"></i>Dashboard</a>
            <a class="<?= $page === 'list' ? 'active' : '' ?>" href="index.php?page=list"><i data-lucide="table-2"></i>Daftar Kota</a>
            <a class="<?= $page === 'add' ? 'active' : '' ?>" href="index.php?page=add"><i data-lucide="plus-circle"></i>Tambah Kota</a>
            <a class="<?= $page === 'about' ? 'active' : '' ?>" href="index.php?page=about"><i data-lucide="file-text"></i>Dokumentasi</a>
        </nav>
    </aside>

    <main class="app-shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Final Project Web Service</p>
                <h1><?= $page === 'dashboard' ? 'Dashboard Cuaca Kota' : h(ucfirst($page)) ?></h1>
            </div>
            <a class="primary-action" href="index.php?page=add"><i data-lucide="plus"></i>Tambah Data</a>
        </header>

        <?php if ($flash): ?>
            <div class="notice"><?= h($flash) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= h($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <section class="hero-board">
                <div class="hero-copy">
                    <span class="status-pill"><i data-lucide="radio"></i>Live dari Open-Meteo API</span>
                    <h2>Pantau kota favorit, kelola datanya, dan tampilkan cuaca real-time dalam satu dashboard.</h2>
                    <p>Project ini menggabungkan CRUD PHP Native, database lokal JSON, pencarian data, dan konsumsi API eksternal untuk demo UAS Web Service.</p>
                    <div class="hero-actions">
                        <a class="primary-action inline" href="index.php?page=list"><i data-lucide="table-2"></i>Lihat Data</a>
                        <a class="ghost-action" href="index.php?page=about"><i data-lucide="book-open"></i>Dokumentasi</a>
                    </div>
                </div>
                <div class="hero-weather weather-summary" data-city="<?= h($mainCity['name'] ?? 'Jakarta') ?>">
                    <div class="hero-weather-top">
                        <div>
                            <span>Kota utama</span>
                            <strong><?= h($mainCity['name'] ?? 'Jakarta') ?></strong>
                        </div>
                        <i data-lucide="cloud-sun"></i>
                    </div>
                    <div id="dashboard-temp" class="hero-temp">Memuat</div>
                    <p id="dashboard-weather">Mengambil data cuaca terbaru...</p>
                </div>
            </section>

            <section class="stat-grid">
                <article class="stat-card">
                    <i data-lucide="map-pinned"></i>
                    <div>
                        <span>Total Kota</span>
                        <strong><?= $totalCities ?></strong>
                        <small>Data lokal yang bisa di-CRUD</small>
                    </div>
                </article>
                <article class="stat-card">
                    <i data-lucide="globe-2"></i>
                    <div>
                        <span>Total Negara</span>
                        <strong><?= $totalCountries ?></strong>
                        <small>Ringkasan dari daftar kota</small>
                    </div>
                </article>
                <article class="stat-card">
                    <i data-lucide="layers-3"></i>
                    <div>
                        <span>Kategori</span>
                        <strong><?= count($categories) ?></strong>
                        <small>Filter data siap dipakai</small>
                    </div>
                </article>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">API eksternal</p>
                        <h2>Cuaca real-time kota favorit</h2>
                    </div>
                    <a class="ghost-action" href="index.php?page=list">Kelola Kota</a>
                </div>
                <div class="weather-grid">
                    <?php foreach (array_slice($cities, 0, 6) as $city): ?>
                        <article class="weather-card" data-city="<?= h($city['name']) ?>">
                            <div class="weather-card-head">
                                <i data-lucide="cloud"></i>
                                <span class="badge"><?= h($city['category']) ?></span>
                            </div>
                            <div>
                                <h3><?= h($city['name']) ?></h3>
                                <p><?= h($city['country']) ?></p>
                            </div>
                            <div class="weather-value">Memuat</div>
                            <small>Data dari Open-Meteo</small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($page === 'list'): ?>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Read, Search, Filter</p>
                        <h2>Daftar data kota favorit</h2>
                    </div>
                    <span class="record-count"><?= count($filteredCities) ?> data tampil</span>
                </div>
                <form class="filter-bar" method="get">
                    <input type="hidden" name="page" value="list">
                    <input type="search" name="q" value="<?= h($query) ?>" placeholder="Cari kota atau negara">
                    <select name="category">
                        <option value="">Semua kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= h($category) ?>" <?= $filter === $category ? 'selected' : '' ?>><?= h($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><i data-lucide="search"></i>Cari</button>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Kota</th>
                                <th>Negara</th>
                                <th>Kategori</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredCities as $city): ?>
                                <tr>
                                    <td><?= h($city['name']) ?></td>
                                    <td><?= h($city['country']) ?></td>
                                    <td><span class="badge"><?= h($city['category']) ?></span></td>
                                    <td><?= h($city['notes'] ?? '-') ?></td>
                                    <td class="actions">
                                        <a href="index.php?page=detail&id=<?= (int) $city['id'] ?>" title="Detail"><i data-lucide="eye"></i></a>
                                        <a href="index.php?page=edit&id=<?= (int) $city['id'] ?>" title="Edit"><i data-lucide="pencil"></i></a>
                                        <form method="post" onsubmit="return confirm('Hapus data kota ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $city['id'] ?>">
                                            <button type="submit" title="Hapus"><i data-lucide="trash-2"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$filteredCities): ?>
                                <tr><td colspan="5" class="empty">Tidak ada data yang sesuai.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($page === 'add' || $page === 'edit'): ?>
            <section class="panel form-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow"><?= $page === 'add' ? 'Create' : 'Update' ?></p>
                        <h2><?= $page === 'add' ? 'Tambah data kota' : 'Edit data kota' ?></h2>
                    </div>
                </div>
                <form class="city-form" method="post">
                    <input type="hidden" name="action" value="<?= $page === 'add' ? 'create' : 'update' ?>">
                    <?php if ($page === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= (int) ($_GET['id'] ?? 0) ?>">
                    <?php endif; ?>
                    <label>
                        Nama Kota
                        <input required minlength="2" name="name" value="<?= h($formData['name']) ?>" placeholder="Contoh: Bandung">
                    </label>
                    <label>
                        Negara
                        <input required name="country" value="<?= h($formData['country']) ?>" placeholder="Contoh: Indonesia">
                    </label>
                    <label>
                        Kategori
                        <select required name="category">
                            <?php foreach (['Ibukota', 'Bisnis', 'Wisata', 'Pendidikan', 'Favorit'] as $category): ?>
                                <option value="<?= h($category) ?>" <?= $formData['category'] === $category ? 'selected' : '' ?>><?= h($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full">
                        Catatan
                        <textarea name="notes" rows="4" placeholder="Keterangan singkat kota"><?= h($formData['notes']) ?></textarea>
                    </label>
                    <div class="form-actions">
                        <a class="ghost-action" href="index.php?page=list">Batal</a>
                        <button type="submit"><i data-lucide="save"></i>Simpan</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($page === 'detail' && $selectedCity): ?>
            <section class="detail-layout">
                <article class="panel">
                    <p class="eyebrow">Detail Data</p>
                    <h2><?= h($selectedCity['name']) ?></h2>
                    <dl class="detail-list">
                        <div><dt>Negara</dt><dd><?= h($selectedCity['country']) ?></dd></div>
                        <div><dt>Kategori</dt><dd><?= h($selectedCity['category']) ?></dd></div>
                        <div><dt>Catatan</dt><dd><?= h($selectedCity['notes'] ?? '-') ?></dd></div>
                    </dl>
                    <a class="primary-action inline" href="index.php?page=edit&id=<?= (int) $selectedCity['id'] ?>"><i data-lucide="pencil"></i>Edit Data</a>
                </article>
                <article class="panel live-weather" data-city="<?= h($selectedCity['name']) ?>">
                    <p class="eyebrow">Open-Meteo API</p>
                    <h2>Cuaca saat ini</h2>
                    <div class="live-temp">Memuat</div>
                    <p class="live-desc">Mengambil data cuaca real-time...</p>
                </article>
            </section>
        <?php endif; ?>

        <?php if ($page === 'about'): ?>
            <section class="panel docs">
                <p class="eyebrow">Dokumentasi singkat</p>
                <h2>Sistem Informasi Cuaca dan Kota</h2>
                <p>Project ini memenuhi instruksi UAS Web Service: PHP Native, CRUD data kota favorit, dashboard, daftar data, form tambah/edit, hapus data, search/filter, dan integrasi API eksternal Open-Meteo.</p>
                <div class="docs-grid">
                    <div><strong>Data utama</strong><span>Kota favorit disimpan di <code>data/cities.json</code>.</span></div>
                    <div><strong>API</strong><span><code>api.php</code> mengambil geocoding dan cuaca dari Open-Meteo.</span></div>
                    <div><strong>Halaman</strong><span>Dashboard, daftar data, tambah, edit, detail, dan dokumentasi.</span></div>
                    <div><strong>Validasi</strong><span>Nama kota, negara, dan kategori wajib diisi.</span></div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <script src="script.js"></script>
</body>
</html>

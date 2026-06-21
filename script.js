const weatherCodes = {
    0: 'Cerah',
    1: 'Sebagian cerah',
    2: 'Berawan',
    3: 'Mendung',
    45: 'Berkabut',
    48: 'Kabut tebal',
    51: 'Gerimis ringan',
    53: 'Gerimis sedang',
    55: 'Gerimis lebat',
    61: 'Hujan ringan',
    63: 'Hujan sedang',
    65: 'Hujan lebat',
    71: 'Salju ringan',
    73: 'Salju sedang',
    75: 'Salju lebat',
    95: 'Badai petir',
    96: 'Badai petir ringan',
    99: 'Badai petir lebat'
};

if (window.lucide) {
    lucide.createIcons();
}

function describeWeather(code) {
    return weatherCodes[code] || 'Tidak diketahui';
}

async function fetchWeather(city) {
    const response = await fetch(`api.php?city=${encodeURIComponent(city)}`);
    if (!response.ok) {
        throw new Error('Cuaca tidak tersedia');
    }
    return response.json();
}

function renderCardWeather(card, data) {
    const current = data.current || {};
    const temp = Math.round(current.temperature_2m);
    const desc = describeWeather(current.weather_code);
    const valueEl = card.querySelector('.weather-value');
    if (valueEl) {
        valueEl.textContent = `${Number.isFinite(temp) ? temp : '--'}\u00B0C`;
    }
    const noteEl = card.querySelector('small');
    if (noteEl) {
        noteEl.textContent = `${desc} - Angin ${current.wind_speed_10m ?? '--'} km/h`;
    }
}

document.querySelectorAll('.weather-card[data-city]').forEach(async (card) => {
    try {
        const data = await fetchWeather(card.dataset.city);
        renderCardWeather(card, data);
    } catch (error) {
        const valueEl = card.querySelector('.weather-value');
        if (valueEl) valueEl.textContent = 'Gagal';
    }
});

const summary = document.querySelector('.weather-summary[data-city]');
if (summary) {
    fetchWeather(summary.dataset.city)
        .then((data) => {
            const current = data.current || {};
            const temp = Math.round(current.temperature_2m);
            document.getElementById('dashboard-temp').textContent = `${Number.isFinite(temp) ? temp : '--'}\u00B0C`;
            document.getElementById('dashboard-weather').textContent = describeWeather(current.weather_code);
        })
        .catch(() => {
            document.getElementById('dashboard-temp').textContent = 'Gagal';
            document.getElementById('dashboard-weather').textContent = 'API tidak tersedia';
        });
}

const liveWeather = document.querySelector('.live-weather[data-city]');
if (liveWeather) {
    fetchWeather(liveWeather.dataset.city)
        .then((data) => {
            const current = data.current || {};
            const temp = Math.round(current.temperature_2m);
            liveWeather.querySelector('.live-temp').textContent = `${Number.isFinite(temp) ? temp : '--'}\u00B0C`;
            liveWeather.querySelector('.live-desc').textContent = `${describeWeather(current.weather_code)} dengan kelembapan ${current.relative_humidity_2m ?? '--'}% dan tekanan ${current.surface_pressure ?? '--'} hPa.`;
        })
        .catch(() => {
            liveWeather.querySelector('.live-temp').textContent = 'Gagal';
            liveWeather.querySelector('.live-desc').textContent = 'Data cuaca belum bisa dimuat.';
        });
}

// Mobile Menu
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const closeMenuBtn = document.getElementById('closeMenuBtn');
const navOverlay = document.getElementById('navOverlay');
const mainNav = document.getElementById('mainNav');
const navLinks = document.querySelectorAll('.nav__link');

function openNav() {
    mainNav.classList.add('active');
    navOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeNav() {
    mainNav.classList.remove('active');
    navOverlay.classList.remove('active');
    document.body.style.overflow = 'auto';
}

mobileMenuBtn?.addEventListener('click', openNav);
closeMenuBtn?.addEventListener('click', closeNav);
navOverlay?.addEventListener('click', closeNav);

navLinks.forEach(link => {
    link.addEventListener('click', closeNav);
});

// Header scroll effect
window.addEventListener('scroll', () => {
    const header = document.querySelector('.header');
    if (window.scrollY > 50) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }
});

// Gold Price Tracker
class GoldPriceTracker {
    constructor() {
        this.prices = {
            '999': 0,
            '585': 0
        };
        
        this.priceElements = {
            '999': document.getElementById('gold-price-999'),
            '585': document.getElementById('gold-price-585')
        };
        
        this.updateTimeElement = document.querySelector('.update-time');
        this.refreshButton = document.getElementById('refreshGoldPrice');
        
        // Используем бесплатный публичный API Goldprice.org
        this.apis = [
            {
                name: 'goldprice',
                url: 'https://data-asg.goldprice.org/dbXRates/EUR',
                parser: (data) => {
                    if (data && data.items && data.items[0]) {
                        // Цена в EUR за тройскую унцию
                        const eurPerOunce = parseFloat(data.items[0].xauPrice);
                        // Конвертируем в граммы (1 тр. унция = 31.1035 грамм)
                        const price999 = eurPerOunce / 31.1035;
                        const price585 = price999 * 0.585;
                        return { '999': price999, '585': price585 };
                    }
                    return null;
                }
            },
            {
                name: 'nbp',
                url: 'https://api.nbp.pl/api/cenyzlota/last/1/?format=json',
                parser: (data) => {
                    if (data && data[0] && data[0].cena) {
                        // Цена в PLN за грамм, конвертируем в EUR (примерно 1 PLN = 0.23 EUR)
                        const pricePLN = parseFloat(data[0].cena);
                        const price999 = pricePLN * 0.23;
                        const price585 = price999 * 0.585;
                        return { '999': price999, '585': price585 };
                    }
                    return null;
                }
            }
        ];
        
        this.currentApiIndex = 0;
        
        this.init();
    }
    
    init() {
        this.fetchGoldPrices();
        this.setupEventListeners();
        this.startAutoUpdate();
    }
    
    updateTimestamp() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('de-DE', {
            hour: '2-digit',
            minute: '2-digit'
        });
        this.updateTimeElement.textContent = `Aktualisiert: ${timeString}`;
    }
    
    updateDisplay() {
        if (this.prices['999'] > 0) {
            this.priceElements['999'].textContent = `€ ${this.prices['999'].toFixed(2)}`;
            this.priceElements['585'].textContent = `€ ${this.prices['585'].toFixed(2)}`;
        }
    }
    
    setupEventListeners() {
        this.refreshButton.addEventListener('click', () => {
            this.fetchGoldPrices();
            this.refreshButton.style.transform = 'rotate(180deg)';
            setTimeout(() => {
                this.refreshButton.style.transform = 'rotate(0deg)';
            }, 300);
        });
    }
    
    async fetchGoldPrices() {
        try {
            // Показываем индикатор загрузки
            this.priceElements['999'].textContent = '...';
            this.priceElements['585'].textContent = '...';
            
            // Пробуем текущий API
            const currentApi = this.apis[this.currentApiIndex];
            
            const fetchOptions = currentApi.headers ? {
                headers: currentApi.headers
            } : {};
            
            const response = await fetch(currentApi.url, fetchOptions);
            const data = await response.json();
            
            // Парсим данные с помощью специфичной для API функции
            const prices = currentApi.parser(data);
            
            if (prices && prices['999'] > 0 && prices['585'] > 0) {
                // Устанавливаем реальные цены из API
                this.prices['999'] = prices['999'];
                this.prices['585'] = prices['585'];
                
                this.updateTimestamp();
                this.updateDisplay();
                
                console.log(`✅ Цены загружены через ${currentApi.name}:`, 
                    `999: €${prices['999'].toFixed(2)}/г, 585: €${prices['585'].toFixed(2)}/г`);
            } else {
                // Пробуем следующий API
                throw new Error(`API ${currentApi.name} вернул некорректные данные`);
            }
        } catch (error) {
            console.error(`❌ Ошибка с API ${this.apis[this.currentApiIndex].name}:`, error);
            
            // Переключаемся на следующий API
            this.currentApiIndex = (this.currentApiIndex + 1) % this.apis.length;
            
            // Если прошли все API, используем резервные значения
            if (this.currentApiIndex === 0) {
                console.warn('⚠️ Все API недоступны, используем резервные значения');
                this.useFallbackPrices();
            } else {
                // Пробуем следующий API
                console.log(`🔄 Переключаемся на ${this.apis[this.currentApiIndex].name}`);
                await this.fetchGoldPrices();
            }
        }
    }
    
    useFallbackPrices() {
        // Резервные значения, если API недоступен
        this.prices['999'] = 61.23;
        this.prices['585'] = 35.78;
        this.updateTimestamp();
        this.updateDisplay();
    }
    
    startAutoUpdate() {
        // Обновляем цены каждые 5 минут (300000 мс)
        setInterval(() => {
            this.fetchGoldPrices();
        }, 300000);
    }
}

// File Upload
const fileUploadArea = document.getElementById('fileUploadArea');
const fileInput = document.getElementById('photos');
const fileList = document.getElementById('file-list');

fileUploadArea.addEventListener('click', () => {
    fileInput.click();
});

fileUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadArea.style.borderColor = 'var(--color-gold)';
    fileUploadArea.style.backgroundColor = 'rgba(201, 168, 106, 0.05)';
});

fileUploadArea.addEventListener('dragleave', () => {
    fileUploadArea.style.borderColor = '';
    fileUploadArea.style.backgroundColor = '';
});

fileUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadArea.style.borderColor = '';
    fileUploadArea.style.backgroundColor = '';
    
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        handleFiles();
    }
});

fileInput.addEventListener('change', handleFiles);

function handleFiles() {
    fileList.innerHTML = '';
    const files = fileInput.files;
    
    if (files.length > 5) {
        alert('Maximal 5 Dateien erlaubt');
        fileInput.value = '';
        return;
    }
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div class="file-item__info">
                <i class="fas fa-image"></i>
                <div>
                    <div>${file.name}</div>
                    <small>${formatFileSize(file.size)}</small>
                </div>
            </div>
            <button type="button" class="remove-file" data-index="${i}">
                <i class="fas fa-times"></i>
            </button>
        `;
        fileList.appendChild(fileItem);
    }
    
    // Add remove functionality
    document.querySelectorAll('.remove-file').forEach(btn => {
        btn.addEventListener('click', function() {
            const index = parseInt(this.getAttribute('data-index'));
            removeFile(index);
        });
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function removeFile(index) {
    const dt = new DataTransfer();
    const files = fileInput.files;
    
    for (let i = 0; i < files.length; i++) {
        if (i !== index) {
            dt.items.add(files[i]);
        }
    }
    
    fileInput.files = dt.files;
    handleFiles();
}

// Form Submission
const valuationForm = document.getElementById('valuationForm');
valuationForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = valuationForm.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Disable button and show loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wird gesendet...';
    submitBtn.disabled = true;
    
    try {
        // Собираем данные формы
        const formData = {
            name: document.getElementById('name').value,
            phone: document.getElementById('phone').value,
            email: document.getElementById('email').value || 'Nicht angegeben',
            category: document.getElementById('category').value,
            message: document.getElementById('message').value || 'Keine Beschreibung',
            to_email: 'shonraprince@gmail.com'
        };
        
        // Обрабатываем файлы
        const files = fileInput.files;
        let attachments = [];
        
        if (files.length > 0) {
            // Конвертируем файлы в base64
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const base64 = await fileToBase64(file);
                attachments.push({
                    name: file.name,
                    data: base64.split(',')[1] // Убираем префикс data:image/...;base64,
                });
            }
            formData.attachments = JSON.stringify(attachments);
            formData.files_info = `${files.length} Datei(en): ${Array.from(files).map(f => f.name).join(', ')}`;
        } else {
            formData.files_info = 'Keine Dateien angehängt';
        }
        
        // Отправляем через PHP на сервере
        const phpFormData = new FormData();
        phpFormData.append('name', formData.name);
        phpFormData.append('phone', formData.phone);
        phpFormData.append('email', formData.email);
        phpFormData.append('category', formData.category);
        phpFormData.append('message', formData.message);
        
        // Добавляем файлы
        for (let i = 0; i < files.length; i++) {
            phpFormData.append('files[]', files[i]);
        }
        
        // Отправляем на PHP-скрипт
        const response = await fetch('send-email.php', {
            method: 'POST',
            body: phpFormData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Fehler beim Senden');
        }
        
        console.log('✅ Email отправлен:', result);
        
        // Show success message
        showNotification('Erfolg! Ihre Anfrage wurde gesendet. Wir melden uns innerhalb von 24 Stunden.', 'success');
        
        // Reset form
        valuationForm.reset();
        fileList.innerHTML = '';
        
    } catch (error) {
        console.error('❌ Ошибка отправки:', error);
        showNotification('Fehler beim Senden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.', 'error');
    } finally {
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Функция для конвертации файла в base64
function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = () => resolve(reader.result);
        reader.onerror = error => reject(error);
    });
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification notification--${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
        <button class="notification__close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Add notification styles
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 20px;
                border-radius: var(--radius-md);
                box-shadow: var(--shadow-lg);
                display: flex;
                align-items: center;
                gap: 15px;
                z-index: 2000;
                transform: translateX(400px);
                transition: transform 0.3s ease;
                max-width: 400px;
                border-left: 4px solid;
            }
            
            .notification--success {
                border-left-color: #10b981;
            }
            
            .notification--success i {
                color: #10b981;
            }
            
            .notification--error {
                border-left-color: #ef4444;
            }
            
            .notification--error i {
                color: #ef4444;
            }
            
            .notification__close {
                background: none;
                border: none;
                color: var(--color-gray);
                cursor: pointer;
                margin-left: auto;
            }
            
            .notification.show {
                transform: translateX(0);
            }
        `;
        document.head.appendChild(style);
    }
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Auto remove
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
    
    // Close button
    notification.querySelector('.notification__close').addEventListener('click', () => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    });
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href === '#') return;
        
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
            const headerHeight = document.querySelector('.header').offsetHeight;
            const targetPosition = target.offsetTop - headerHeight - 20;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
            
            // Close mobile menu if open
            if (mainNav.classList.contains('active')) {
                mainNav.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }
    });
});

// Video autoplay for mobile
const heroVideo = document.querySelector('.hero__video video');
if (heroVideo) {
    heroVideo.setAttribute('playsinline', '');
    heroVideo.setAttribute('muted', '');
    
    document.addEventListener('DOMContentLoaded', () => {
        heroVideo.play().catch(e => {
            console.log('Video autoplay failed:', e);
        });
    });
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    // Initialize gold price tracker
    const goldPriceTracker = new GoldPriceTracker();
    
    // Animate elements on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
            }
        });
    }, { threshold: 0.1 });
    
    // Observe elements
    document.querySelectorAll('.category-card, .feature, .step').forEach(el => {
        observer.observe(el);
    });
});
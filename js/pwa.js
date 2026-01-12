// PWA Registration and Installation with Error Handling
class PWAHelper {
    constructor() {
        this.deferredPrompt = null;
        this.isPwaSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.init();
    }

    async init() {
        if (!this.isPwaSupported) {
            console.log('PWA features not supported in this browser');
            return;
        }

        await this.registerServiceWorker();
        this.setupInstallPrompt();
    }

async registerServiceWorker() {
    try {
        // Use the correct path found by debugger
        const projectPath = '/gebsco/'; // or whatever the debugger finds
        const registration = await navigator.serviceWorker.register(projectPath + 'sw.js');
        
        return registration;

    } catch (error) {
        console.error('❌ Service Worker registration failed:', error);
    }
}

    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('Before install prompt fired');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallPromotion();
        });

        window.addEventListener('appinstalled', () => {
            console.log('PWA was successfully installed');
            this.hideInstallPromotion();
            this.showMessage('App installed successfully!');
        });
    }

    showInstallPromotion() {
        // Only show if not already installed and not in standalone mode
        if (window.matchMedia('(display-mode: standalone)').matches) {
            return;
        }

        if (!document.getElementById('pwa-install-btn')) {
            const installBtn = document.createElement('button');
            installBtn.id = 'pwa-install-btn';
            installBtn.innerHTML = '📱 Install App';
            installBtn.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 12px 16px;
                background: #28a745;
                color: white;
                border: none;
                border-radius: 25px;
                cursor: pointer;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                font-size: 14px;
                font-weight: bold;
                transition: all 0.3s ease;
            `;
            
            installBtn.onmouseenter = () => {
                installBtn.style.transform = 'scale(1.05)';
                installBtn.style.background = '#218838';
            };
            installBtn.onmouseleave = () => {
                installBtn.style.transform = 'scale(1)';
                installBtn.style.background = '#28a745';
            };
            
            installBtn.onclick = () => this.installApp();
            document.body.appendChild(installBtn);
        }
    }

    hideInstallPromotion() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.remove();
        }
    }

    async installApp() {
        if (!this.deferredPrompt) {
            this.showError('Installation not available. Please use your browser\'s menu to install the app.');
            return;
        }

        try {
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log(`User response to the install prompt: ${outcome}`);
            
            if (outcome === 'accepted') {
                this.showMessage('Installing app...');
            }
            
            this.deferredPrompt = null;
            this.hideInstallPromotion();
            
        } catch (error) {
            console.error('Installation failed:', error);
            this.showError('Installation failed. Please try again.');
        }
    }

    showMessage(message) {
        // Simple notification system
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: #28a745;
            color: white;
            border-radius: 5px;
            z-index: 10001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    showError(message) {
        const error = document.createElement('div');
        error.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: #dc3545;
            color: white;
            border-radius: 5px;
            z-index: 10001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        error.textContent = message;
        document.body.appendChild(error);
        
        setTimeout(() => {
            error.remove();
        }, 5000);
    }

    // Check if app is running in standalone mode
    isRunningAsPwa() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.navigator.standalone === true;
    }
}

// Initialize PWA when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.pwa = new PWAHelper();
});

// Manual install trigger
function installPWA() {
    if (window.pwa) {
        window.pwa.installApp();
    } else {
        alert('PWA features are still loading. Please wait a moment and try again.');
    }
}

// Check if page is loaded in standalone mode
if (window.matchMedia('(display-mode: standalone)').matches) {
    console.log('Running as installed PWA');
    document.documentElement.classList.add('pwa-standalone');
}
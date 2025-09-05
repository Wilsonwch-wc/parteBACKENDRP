// Gestor de productos offline
class OfflineProductManager {
    constructor() {
        this.offlineProducts = [];
        this.isOnline = true;
        this.init();
    }

    init() {
        // Cargar productos pendientes
        const storedProducts = localStorage.getItem('offlineProducts');
        if (storedProducts) {
            try {
                this.offlineProducts = JSON.parse(storedProducts);
                if (this.offlineProducts.length > 0) {
                    this.showNotification(`Hay ${this.offlineProducts.length} productos pendientes de sincronizar.`, 'info');
                }
            } catch (e) {
                console.error('Error al cargar productos offline:', e);
                localStorage.removeItem('offlineProducts');
                this.offlineProducts = [];
            }
        }

        // Usar el estado de conexión del offlineManager principal
        if (typeof offlineManager !== 'undefined') {
            this.isOnline = offlineManager.isOnline;
        } else {
            this.isOnline = navigator.onLine;
            // Si no existe offlineManager, configuramos nuestros propios listeners
            window.addEventListener('online', () => this.handleConnectionChange());
            window.addEventListener('offline', () => this.handleConnectionChange());
        }

        // Modificar el comportamiento del formulario de productos
        this.setupFormBehavior();
    }

    // Usar el estado de conexión global si está disponible
    handleConnectionChange() {
        if (typeof offlineManager !== 'undefined') {
            this.isOnline = offlineManager.isOnline;
        } else {
            this.isOnline = navigator.onLine;
        }

        if (this.isOnline && this.offlineProducts.length > 0) {
            this.showNotification(`Conexión restablecida. Sincronizando ${this.offlineProducts.length} productos...`, 'success');
            this.syncOfflineProducts();
        }
    }

    // Configurar el comportamiento del formulario
    setupFormBehavior() {
        const form = document.getElementById('productoForm');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            // Si estamos online, el formulario se envía normalmente
            if (this.isOnline) return;

            // Si estamos offline, capturamos el envío
            e.preventDefault();
            this.saveProductOffline(form);
        });
    }

    // Guardar producto en modo offline
    async saveProductOffline(form) {
        const formData = new FormData(form);
        const product = {};
        
        // Convertir FormData a objeto
        for (let [key, value] of formData.entries()) {
            if (key !== 'imagenes') {
                product[key] = value;
            }
        }

        // Generar ID temporal
        product.id = 'offline_' + Date.now();
        product.timestamp = new Date().toISOString();
        
        // Procesar imágenes
        product.images = [];
        const fileInput = form.querySelector('input[type="file"]');
        if (fileInput && fileInput.files.length > 0) {
            for (let i = 0; i < fileInput.files.length; i++) {
                try {
                    const file = fileInput.files[i];
                    const base64 = await this.fileToBase64(file);
                    product.images.push({
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        data: base64
                    });
                } catch (error) {
                    console.error('Error procesando imagen:', error);
                }
            }
        }

        // Guardar en array y localStorage
        this.offlineProducts.push(product);
        localStorage.setItem('offlineProducts', JSON.stringify(this.offlineProducts));
        
        // Notificar al usuario
        Swal.fire({
            title: '¡Producto guardado localmente!',
            text: 'El producto se ha guardado en modo sin conexión y se sincronizará cuando vuelva la conexión a internet.',
            icon: 'success',
            confirmButtonText: 'Entendido'
        });
        
        // Reiniciar el formulario
        form.reset();
    }

    // Convertir File a Base64
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    }
    
    // Sincronizar productos pendientes
    async syncOfflineProducts() {
        if (this.offlineProducts.length === 0) return;
        
        const totalProducts = this.offlineProducts.length;
        let syncedCount = 0;
        let failedCount = 0;
        
        // Crear copia para no modificar durante la iteración
        const productsToSync = [...this.offlineProducts];
        
        for (const product of productsToSync) {
            try {
                // Crear FormData para enviar
                const formData = new FormData();
                
                // Agregar datos del producto
                for (const [key, value] of Object.entries(product)) {
                    if (key !== 'images' && key !== 'id' && key !== 'timestamp') {
                        formData.append(key, value);
                    }
                }
                
                // Agregar token CSRF si existe
                if (document.querySelector('input[name="csrf_token"]')) {
                    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                }
                
                // Agregar imágenes
                if (product.images && product.images.length > 0) {
                    for (let i = 0; i < product.images.length; i++) {
                        const img = product.images[i];
                        // Convertir base64 a Blob
                        const blob = await this.base64ToBlob(img.data, img.type);
                        formData.append(`imagenes[${i}]`, blob, img.name);
                    }
                }
                
                // Enviar al servidor
                const response = await fetch('procesar_producto_offline.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Producto sincronizado correctamente
                    this.offlineProducts = this.offlineProducts.filter(p => p.id !== product.id);
                    localStorage.setItem('offlineProducts', JSON.stringify(this.offlineProducts));
                    syncedCount++;
                } else {
                    console.error('Error al sincronizar producto:', result.error);
                    failedCount++;
                }
            } catch (error) {
                console.error('Error de red al sincronizar producto:', error);
                failedCount++;
            }
        }
        
        // Mostrar resultado
        if (syncedCount > 0 || failedCount > 0) {
            const message = `Sincronización de productos: ${syncedCount} sincronizados, ${failedCount} fallidos.`;
            this.showNotification(message, failedCount > 0 ? 'warning' : 'success');
        }
    }
    
    // Convertir Base64 a Blob
    async base64ToBlob(base64, type) {
        const response = await fetch(base64);
        const blob = await response.blob();
        return new Blob([blob], { type: type });
    }

    // Mostrar notificación
    showNotification(message, type = 'info') {
        if (typeof mostrarMensaje === 'function') {
            mostrarMensaje(message, type);
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: type === 'success' ? '¡Éxito!' : type === 'warning' ? 'Advertencia' : 'Información',
                text: message,
                icon: type,
                confirmButtonText: 'Entendido'
            });
        } else {
            alert(message);
        }
    }
}

// Iniciar gestor de productos offline
const offlineProductManager = new OfflineProductManager(); 
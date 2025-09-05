// Gestor de ventas offline
class OfflineManager {
    constructor() {
        this.offlineVentas = [];
        this.isOnline = true;
        this.checkingConnection = false;
        this.init();
    }

    init() {
        // Cargar ventas pendientes
        const storedVentas = localStorage.getItem('offlineVentas');
        if (storedVentas) {
            try {
                this.offlineVentas = JSON.parse(storedVentas);
                if (this.offlineVentas.length > 0) {
                    this.updateOfflineCounter();
                    this.showNotification(`Hay ${this.offlineVentas.length} ventas pendientes de sincronizar.`, 'info');
                }
            } catch (e) {
                console.error('Error al cargar ventas offline:', e);
                localStorage.removeItem('offlineVentas');
                this.offlineVentas = [];
            }
        }

        // Verificar conexión inmediatamente y cada 30 segundos
        this.checkServerConnection();
        setInterval(() => this.checkServerConnection(), 30000);

        // Configurar listeners para eventos de conexión
        window.addEventListener('online', this.handleConnectionChange.bind(this));
        window.addEventListener('offline', this.handleConnectionChange.bind(this));
    }

    // Comprueba si el servidor está disponible
    async checkServerConnection() {
        if (this.checkingConnection) return;
        this.checkingConnection = true;

        try {
            // Enviamos un pequeño request a un endpoint que responda rápido
            const response = await fetch('ping.php?t=' + new Date().getTime(), {
                method: 'HEAD',
                cache: 'no-store',
                timeout: 3000 // 3 segundos de timeout
            });

            const newStatus = response.ok;
            
            // Si cambió el estado de la conexión
            if (this.isOnline !== newStatus) {
                this.isOnline = newStatus;
                this.updateConnectionStatus();
                
                if (this.isOnline && this.offlineVentas.length > 0) {
                    this.showNotification(`Conexión restaurada. Sincronizando ${this.offlineVentas.length} ventas...`, 'success');
                    await this.syncOfflineVentas();
                } else if (!this.isOnline) {
                    this.showNotification('Modo sin conexión activado. Las ventas se guardarán localmente.', 'warning');
                }
            }
        } catch (error) {
            // Si hay un error en la petición, asumimos que estamos offline
            if (this.isOnline) {
                this.isOnline = false;
                this.updateConnectionStatus();
                this.showNotification('Modo sin conexión activado. Las ventas se guardarán localmente.', 'warning');
            }
        } finally {
            this.checkingConnection = false;
        }
    }

    // Actualiza la UI para reflejar el estado de conexión
    updateConnectionStatus() {
        const onlineIndicator = document.getElementById('onlineIndicator');
        const offlineIndicator = document.getElementById('offlineIndicator');
        
        if (!onlineIndicator || !offlineIndicator) return;
        
        if (this.isOnline) {
            onlineIndicator.classList.remove('d-none');
            offlineIndicator.classList.add('d-none');
        } else {
            onlineIndicator.classList.add('d-none');
            offlineIndicator.classList.remove('d-none');
        }
    }

    // Maneja los cambios en la conexión del navegador
    handleConnectionChange() {
        // Cuando el navegador detecte un cambio, verificamos el servidor
        this.checkServerConnection();
    }

    // Guarda una venta en modo offline
    saveOfflineVenta(items, metodoPago, incluirIVA, incluirIVA21, envio = 0) {
        const ventaId = 'offline_' + Date.now();
        
        // Mantener los ítems originales sin modificar sus totales
        const procesados = items.map(item => ({
            ...item,
            // Guardamos solo las flags de impuestos, sin modificar los totales
            iva: incluirIVA ? 1 : 0,
            iva21: incluirIVA21 ? 1 : 0
        }));
        
        // Convertir el valor de envío a número
        const valorEnvio = parseFloat(envio) || 0;
        
        // Calcular subtotal (sin impuestos)
        const subtotal = procesados.reduce((sum, item) => sum + item.total, 0);
        
        // Calcular impuestos
        const recargoTotal = incluirIVA ? subtotal * 0.035 : 0;
        const iva21Total = incluirIVA21 ? subtotal * 0.21 : 0;
        
        // Calcular el total incluyendo impuestos y envío
        const totalConImpuestos = subtotal + recargoTotal + iva21Total + valorEnvio;
        
        const venta = {
            id: ventaId,
            items: procesados,
            metodoPago: metodoPago,
            timestamp: new Date().toISOString(),
            incluirIVA: incluirIVA,
            incluirIVA21: incluirIVA21,
            subtotal: subtotal,
            total: totalConImpuestos,
            envio: valorEnvio
        };
        
        // Guardar en memoria y localStorage
        this.offlineVentas.push(venta);
        localStorage.setItem('offlineVentas', JSON.stringify(this.offlineVentas));
        
        this.updateOfflineCounter();
        
        // Mostrar ticket directamente
        this.generateOfflineTicket(ventaId);
        
        // Mostrar mensaje de venta exitosa
        this.showNotification('¡Venta guardada exitosamente en modo offline!', 'success');
        
        return ventaId;
    }
    
    // Actualiza el contador de ventas offline pendientes
    updateOfflineCounter() {
        const badge = document.getElementById('offlineIndicator');
        if (badge) {
            if (this.offlineVentas.length > 0) {
                badge.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Sin conexión (${this.offlineVentas.length})`;
            } else {
                badge.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Sin conexión`;
            }
        }
    }
    
    // Sincroniza las ventas pendientes con el servidor
    async syncOfflineVentas() {
        if (this.offlineVentas.length === 0) return;
        
        const totalVentas = this.offlineVentas.length;
        let sincronizadas = 0;
        let fallidas = 0;
        
        // Creamos una copia para no modificar el array original durante el ciclo
        const ventasParaSincronizar = [...this.offlineVentas];
        
        for (const venta of ventasParaSincronizar) {
            try {
                console.log('Sincronizando venta:', venta.id);
                
                const response = await fetch('procesar_venta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        items: venta.items,
                        metodoPago: venta.metodoPago,
                        offline: true,
                        timestamp: venta.timestamp,
                        incluirIVA: venta.incluirIVA,
                        incluirIVA21: venta.incluirIVA21,
                        envio: venta.envio || 0
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('Venta sincronizada con éxito:', venta.id);
                    // Remover la venta de la lista
                    this.offlineVentas = this.offlineVentas.filter(v => v.id !== venta.id);
                    localStorage.setItem('offlineVentas', JSON.stringify(this.offlineVentas));
                    sincronizadas++;
                } else {
                    console.error('Error al sincronizar venta:', venta.id, result.error);
                    fallidas++;
                }
            } catch (error) {
                console.error('Error de red al sincronizar:', venta.id, error);
                fallidas++;
                // Si hay error de red, continuamos con la siguiente venta
                // No detenemos la sincronización
            }
            
            // Actualizar estado
            this.updateOfflineCounter();
        }
        
        // Mostrar resultado
        if (sincronizadas > 0) {
            this.showNotification(`Se sincronizaron ${sincronizadas} de ${totalVentas} ventas pendientes.`, 
                                 fallidas > 0 ? 'warning' : 'success');
        }
        
        // Si fallaron todas, probablemente estamos offline de nuevo
        if (fallidas === totalVentas) {
            this.isOnline = false;
            this.updateConnectionStatus();
        }
        
        return { sincronizadas, fallidas };
    }

    // Muestra una notificación en la interfaz
    showNotification(message, type = 'info') {
        // Buscamos la función global o creamos una alerta simple
        if (typeof mostrarMensaje === 'function') {
            mostrarMensaje(message, type);
        } else {
            alert(message);
        }
    }
    
    // Método para procesar una venta dependiendo del estado de conexión
    async procesarVenta(carritoItems, incluirIVA, incluirIVA21) {
        // Comprobar la conexión antes de procesar
        await this.checkServerConnection();
        
        // Devolvemos un objeto con el estado
        return {
            success: true,
            online: this.isOnline
        };
    }

    // Agregar un método para generar tickets de ventas offline
    generateOfflineTicket(ventaId) {
        // Buscar la venta en el almacenamiento local
        const venta = this.offlineVentas.find(v => v.id === ventaId);
        if (!venta) return;
        
        // Abrir ventana con los datos
        const ticketWindow = window.open('', 'TicketVentaOffline', 
            'width=400,height=600,resizable=yes,scrollbars=yes');
        
        // Enviar datos al endpoint de ticket
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generar_ticket.php';
        form.target = 'TicketVentaOffline';
        
        // Crear campo para enviar datos JSON
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ticketData';
        input.value = JSON.stringify({
            items: venta.items,
            metodoPago: venta.metodoPago,
            timestamp: venta.timestamp,
            incluirIVA: venta.incluirIVA,
            incluirIVA21: venta.incluirIVA21,
            envio: venta.envio || 0
        });
        
        // Añadir al documento y enviar
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}

// Crear la instancia y exportarla
const offlineManager = new OfflineManager(); 
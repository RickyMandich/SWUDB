// Funzioni helper per Alpine.js e Livewire
document.addEventListener('alpine:init', () => {
    // Toast notification system
    Alpine.data('toast', () => ({
        visible: false,
        message: '',
        type: 'info',
        
        show(message, type = 'info', duration = 3000) {
            this.visible = true;
            this.message = message;
            this.type = type;
            
            setTimeout(() => {
                this.hide();
            }, duration);
        },
        
        hide() {
            this.visible = false;
        }
    }));
});
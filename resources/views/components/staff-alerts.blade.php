{{--
    Alert channel for the kitchen and cashier screens.

    Livewire dispatches a browser event carrying `title` and `body`; this plays a
    double beep and raises a desktop notification, so staff are told even when the
    tab is behind another window. Browsers block both sound and notifications until
    the user asks for them, hence the toggle: the click is what unlocks audio, and
    the choice is remembered per device.
--}}
<div x-data="{
        on: false,
        ctx: null,
        init() {
            try { this.on = localStorage.getItem('staffAlerts') === 'on'; } catch (e) {}
            if (this.on) { this.prepare(); }
        },
        async prepare() {
            try {
                this.ctx = this.ctx || new (window.AudioContext || window.webkitAudioContext)();
                if (this.ctx.state === 'suspended') { await this.ctx.resume(); }
            } catch (e) {}
            try {
                if ('Notification' in window && Notification.permission === 'default') {
                    await Notification.requestPermission();
                }
            } catch (e) {}
        },
        async toggle() {
            if (this.on) {
                this.on = false;
                try { localStorage.setItem('staffAlerts', 'off'); } catch (e) {}
                return;
            }
            await this.prepare();
            this.on = true;
            try { localStorage.setItem('staffAlerts', 'on'); } catch (e) {}
            this.beep();
        },
        beep() {
            try {
                this.ctx = this.ctx || new (window.AudioContext || window.webkitAudioContext)();
                [0, 0.25].forEach(offset => {
                    const osc = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();
                    osc.connect(gain); gain.connect(this.ctx.destination);
                    osc.frequency.value = 880;
                    gain.gain.setValueAtTime(0.4, this.ctx.currentTime + offset);
                    gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + offset + 0.2);
                    osc.start(this.ctx.currentTime + offset);
                    osc.stop(this.ctx.currentTime + offset + 0.2);
                });
            } catch (e) {}
        },
        notify(detail) {
            try {
                if (!('Notification' in window) || Notification.permission !== 'granted') { return; }
                const note = new Notification(detail.title || '{{ __('New order') }}', {
                    body: detail.body || '',
                    icon: '/favicon.svg',
                    tag: detail.tag || 'staff-alert',
                    renotify: true,
                });
                note.onclick = () => { window.focus(); note.close(); };
                setTimeout(() => note.close(), 15000);
            } catch (e) {}
        },
        fire(detail) {
            if (!this.on) { return; }
            this.beep();
            this.notify(detail || {});
        },
     }"
     x-on:staff-alert.window="fire($event.detail)"
     {{ $attributes->merge(['class' => 'inline-flex']) }}>

    <button type="button" x-on:click="toggle()"
            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition"
            :class="on ? 'border-green-300 bg-green-50 text-green-800' : 'border-gray-300 bg-white text-gray-600'">
        <span x-text="on ? '🔔' : '🔕'"></span>
        <span x-text="on ? @js(__('Alerts on')) : @js(__('Alerts off'))"></span>
    </button>
</div>

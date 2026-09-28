@props(['event'])

{{-- Plays a short double beep whenever the given browser event is dispatched on window.
     Uses WebAudio so no sound file is needed; browsers require one user interaction first. --}}
<div x-data="{
        beep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [0, 0.25].forEach(offset => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.value = 880;
                    gain.gain.setValueAtTime(0.4, ctx.currentTime + offset);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + offset + 0.2);
                    osc.start(ctx.currentTime + offset); osc.stop(ctx.currentTime + offset + 0.2);
                });
            } catch (e) {}
        }
     }"
     x-on:{{ $event }}.window="beep()"></div>

export class RingtonePlayer {
    constructor() {
        this.audioContext = null;
        this.gainNode = null;
        this.oscillator = null;
        this.intervalId = null;
        this.activeFrequency = 480;
    }

    start() {
        if (this.intervalId !== null) {
            return;
        }

        const AudioContextClass = window.AudioContext ?? window.webkitAudioContext;

        if (!AudioContextClass) {
            return;
        }

        this.audioContext ??= new AudioContextClass();

        if (this.audioContext.state === 'suspended') {
            void this.audioContext.resume();
        }

        this.gainNode = this.audioContext.createGain();
        this.gainNode.gain.value = 0.15;
        this.gainNode.connect(this.audioContext.destination);

        this.playTone(this.activeFrequency);
        this.intervalId = window.setInterval(() => {
            this.activeFrequency = this.activeFrequency === 480 ? 620 : 480;
            this.playTone(this.activeFrequency);
        }, 2000);
    }

    stop() {
        if (this.intervalId !== null) {
            window.clearInterval(this.intervalId);
            this.intervalId = null;
        }

        if (this.oscillator !== null) {
            this.oscillator.stop();
            this.oscillator.disconnect();
            this.oscillator = null;
        }

        if (this.gainNode !== null) {
            this.gainNode.disconnect();
            this.gainNode = null;
        }
    }

    playTone(frequency) {
        if (!this.audioContext || !this.gainNode) {
            return;
        }

        if (this.oscillator !== null) {
            this.oscillator.stop();
            this.oscillator.disconnect();
        }

        this.oscillator = this.audioContext.createOscillator();
        this.oscillator.type = 'sine';
        this.oscillator.frequency.value = frequency;
        this.oscillator.connect(this.gainNode);
        this.oscillator.start();
    }
}

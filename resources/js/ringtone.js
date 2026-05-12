export class RingtonePlayer {
    constructor() {
        this._audio = null;
        this._playing = false;
        this._warnedMissingAudio = false;
    }

    start() {
        if (this._playing) return;

        try {
            if (!this._audio) {
                this._audio = new Audio('/sound/reader.mp3');
                this._audio.loop = true;
                this._audio.volume = 0.7;
                this._audio.addEventListener('error', () => {
                    if (!this._warnedMissingAudio) {
                        console.warn('Ringtone audio /sound/reader.mp3 could not be loaded.');
                        this._warnedMissingAudio = true;
                    }

                    this._playing = false;
                });
            }

            const playPromise = this._audio.play();

            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        this._playing = true;
                    })
                    .catch(() => {
                        this._playing = false;
                    });
            } else {
                this._playing = true;
            }
        } catch {
            this._playing = false;
        }
    }

    stop() {
        if (!this._audio) return;

        try {
            this._audio.pause();
            this._audio.currentTime = 0;
        } catch {
            // Ignore audio cleanup failures.
        }

        this._playing = false;
    }
}

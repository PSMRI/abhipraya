(function (window) {
  'use strict';

  let current = null;

  function stop() {
    if (current) {
      current.pause();
      current.currentTime = 0;
      current = null;
    }

    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
  }

  function playUrl(url) {
    stop();
    current = new Audio(url);
    return current.play();
  }

  function speak(text, language = 'hi-IN') {
    stop();

    if (!('speechSynthesis' in window)) {
      return Promise.reject(new Error('Text-to-speech is not supported.'));
    }

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = language;
    window.speechSynthesis.speak(utterance);

    return Promise.resolve();
  }

  window.AbhiprayaAudio = { playUrl, speak, stop };
})(window);

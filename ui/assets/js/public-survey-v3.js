(function () {
  'use strict';

  const root = document.getElementById('survey-root');
  const alertBox = document.getElementById('survey-alert');
  const parameters = new URLSearchParams(window.location.search);
  const suppliedNin = parameters.get('nin') || '';
  const suppliedDepartment = parameters.get('department') || parameters.get('dept') || '';
  const reference = parameters.get('ref') || (suppliedDepartment ? `${suppliedNin}_${suppliedDepartment}` : suppliedNin);
  const surveyDepartment = (reference.match(/_(\d{1,2})$/) || [])[1] || suppliedDepartment;

  let context = null;
  let questions = [];
  let surveyIdentity = null;
  let language = 1;
  let currentIndex = 0;
  let buttonLabels = { 1: 'Start Survey', 2: 'Previous', 3: 'Next', 4: 'Submit' };
  let verifiedLocation = null;
  const answers = {};
  let activeAudio = null;
  let playbackVersion = 0;
  let cueTimers = [];

  function clearAudioCues() {
    cueTimers.forEach(window.clearTimeout);
    cueTimers = [];
    document.querySelectorAll('.ab-audio-cue').forEach((element) => element.classList.remove('ab-audio-cue'));
  }

  function cue(selector, delay) {
    const timer = window.setTimeout(() => {
      const element = document.querySelector(selector);
      if (!element || element.hidden) return;
      element.classList.add('ab-audio-cue');
      cueTimers.push(window.setTimeout(() => element.classList.remove('ab-audio-cue'), 1300));
    }, delay);
    cueTimers.push(timer);
  }

  function scheduleOpdAudioCues(audio, firstQuestion, selectionAudio) {
    audio.addEventListener('loadedmetadata', () => {
      const duration = Number(audio.duration) || 0;
      if (!duration) return;
      const speakerAt = selectionAudio ? 0.38 : 0.74;
      const nextAt = selectionAudio ? 0.65 : 0.87;
      const backAt = selectionAudio ? 0.84 : 0.95;
      cue('.ab-survey-read-aloud', duration * speakerAt * 1000);
      cue('.ab-survey-nav button:last-child', duration * nextAt * 1000);
      if (!firstQuestion) cue('.ab-survey-nav button:first-child', duration * backAt * 1000);
    }, { once: true });
  }

  function stopPlayback() {
    playbackVersion += 1;
    clearAudioCues();
    if (activeAudio) {
      activeAudio.pause();
      activeAudio.currentTime = 0;
      activeAudio = null;
    }
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
  }

  function apiUrl(path, query) {
    const url = new URL(`/api/v1/public-survey/${path.replace(/\.php$/, '')}`, window.location.origin);
    Object.entries(query || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    return url;
  }

  function showError(message) {
    alertBox.textContent = message;
    alertBox.setAttribute('role', 'alert');
  }

  function clearError() {
    alertBox.textContent = '';
    alertBox.removeAttribute('role');
  }

  function deviceId() {
    const key = 'abhipraya_public_device_id';
    let value = localStorage.getItem(key);
    if (!value) {
      value = window.crypto && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
      localStorage.setItem(key, value);
    }
    return value;
  }

  async function request(url, options) {
    const response = await fetch(url, options);
    const raw = await response.text();
    let payload;
    try {
      payload = raw ? JSON.parse(raw) : {};
    } catch (_) {
      throw new Error('The server returned an invalid response.');
    }
    if (!response.ok || payload.status !== 'success') {
      throw new Error(payload.message || 'Unable to continue.');
    }
    return payload.data;
  }

  function brand(showFacility) {
    const header = document.createElement('header');
    header.className = 'ab-public-head';

    const logo = document.createElement('img');
    logo.className = 'ab-public-logo';
    logo.src = '/ui/assets/img/abhipraya-logo.png';
    logo.alt = 'Abhipraya';
    header.appendChild(logo);

    if (showFacility) {
      const facility = document.createElement('p');
      facility.className = 'ab-public-facility';
      facility.textContent = context.facility.name;
      header.appendChild(facility);

      if (context.facility.address) {
        const address = document.createElement('p');
        address.className = 'ab-public-address';
        address.textContent = context.facility.address;
        header.appendChild(address);
      }
    }
    return header;
  }

  function questionIcon(question) {
    const iconValue = String(question.icon || '').trim();

    // A question package may use an emoji (for example "⏳") instead of an image.
    // Render it as text, never as a broken image URL.
    if (iconValue && !/^(?:assets\/|https?:\/\/)/i.test(iconValue)) {
      const emoji = document.createElement('span');
      emoji.className = 'ab-survey-icon-emoji';
      emoji.textContent = iconValue;
      emoji.setAttribute('aria-hidden', 'true');
      return emoji;
    }

    const image = document.createElement('img');
    image.className = 'ab-survey-icon-image';
    image.alt = '';
    image.setAttribute('aria-hidden', 'true');
    image.referrerPolicy = 'no-referrer';
    const configuredPath = iconValue.replace(/^\/+/, '');
    image.src = /^https?:\/\//i.test(configuredPath)
      ? configuredPath
      : (configuredPath ? `/api/${configuredPath}` : '/ui/assets/img/abhipraya-logo.png');
    image.addEventListener('error', () => {
      image.hidden = true;
    }, { once: true });
    return image;
  }

  function isOpdRating(question) {
    return String(surveyDepartment) === '4' && Number(question.qn) >= 1 && Number(question.qn) <= 10;
  }

  function isOpdSurvey() {
    return String(surveyDepartment) === '4';
  }

  function optionColorIndex(question, option, index) {
    const isNotAvailed = ['सुविधा नहीं ली', 'Did not avail the service'].includes(String(option.text || '').trim());
    let colorIndex = { red: 1, orange: 2, yellow: 3, blue: 4, green: 5, purple: 6, teal: 7, indigo: 8, cyan: 9, amber: 10, slate: 11 }[option.color]
      || (isNotAvailed ? 11 : 0);
    if (!colorIndex && isOpdSurvey()) {
      // Published OPD versions created before color metadata retain this palette.
      colorIndex = isOpdRating(question) && index < 5
        ? index + 1
        : [5, 4, 2, 6, 7, 8, 9][index % 7];
    }
    return colorIndex;
  }

  function optionColorName(question, option, index) {
    const names = language === 2
      ? ['', 'लाल', 'नारंगी', 'पीला', 'नीला', 'हरा', 'बैंगनी', 'टील', 'इंडिगो', 'सियान', 'एम्बर', 'स्लेटी']
      : ['', 'red', 'orange', 'yellow', 'blue', 'green', 'purple', 'teal', 'indigo', 'cyan', 'amber', 'slate'];
    return names[optionColorIndex(question, option, index)] || '';
  }

  function synthesizeQuestion(question) {
    if (!('speechSynthesis' in window)) {
      showError('Read aloud is not supported by this browser.');
      return;
    }
    stopPlayback();
    if (isOpdRating(question)) {
      const firstQuestion = Number(question.qn) === 1;
      const navigation = language === 2
        ? (firstQuestion ? 'दोबारा सुनने के लिए स्पीकर बटन दबाएं। आगे बढ़ने के लिए हरा बटन दबाएं।' : 'दोबारा सुनने के लिए स्पीकर बटन दबाएं। आगे बढ़ने के लिए हरा बटन दबाएं। पीछे जाने के लिए पीला बटन दबाएं।')
        : (firstQuestion ? 'To listen again, press the speaker button. To continue, press the green button.' : 'To listen again, press the speaker button. To continue, press the green button. To go back, press the yellow button.');
      const ratingGuide = language === 2
        ? 'एक स्टार बहुत बुरा, दो सामान्य, तीन अच्छा, चार बहुत अच्छा, और पाँच उत्कृष्ट।'
        : 'One star is very poor, two average, three good, four very good, and five excellent.';
      const speech = new SpeechSynthesisUtterance(`${question.ques} ${ratingGuide} ${navigation}`);
      speech.lang = language === 2 ? 'hi-IN' : 'en-IN';
      window.speechSynthesis.speak(speech);
      return;
    }
    const generatedScript = (question.options || []).map((option, index) => {
      const color = optionColorName(question, option, index);
      return language === 2
        ? `${option.text} के लिए ${color} बटन दबाएं।`
        : `For ${option.text}, press the ${color} button.`;
    }).join(' ');
    const speech = new SpeechSynthesisUtterance(question.voice_script || `${question.ques}. ${generatedScript}`);
    speech.lang = language === 2 ? 'hi-IN' : 'en-IN';
    window.speechSynthesis.speak(speech);
  }

  function speakQuestion(question) {
    stopPlayback();
    const version = playbackVersion;
    if (isOpdRating(question)) {
      const audioLanguage = language === 2 ? 'hi' : 'en';
      const audio = new Audio(`/ui/assets/audio/opd-demo/${audioLanguage}/Q${question.qn}.mp3?v=rating-guide-2`);
      activeAudio = audio;
      scheduleOpdAudioCues(audio, Number(question.qn) === 1, false);
      audio.addEventListener('ended', () => { if (activeAudio === audio) activeAudio = null; }, { once: true });
      audio.addEventListener('error', () => { if (version === playbackVersion) synthesizeQuestion(question); }, { once: true });
      audio.play().catch(() => { if (version === playbackVersion) synthesizeQuestion(question); });
      return;
    }
    const voicePath = String(question.voice_path || '').replace(/^\/+/, '');
    if (!voicePath) {
      synthesizeQuestion(question);
      return;
    }
    const audio = new Audio(`/${voicePath}`);
    activeAudio = audio;
    audio.addEventListener('ended', () => { if (activeAudio === audio) activeAudio = null; }, { once: true });
    let fellBack = false;
    const fallBackToSynthesis = () => {
      if (fellBack) return;
      fellBack = true;
      synthesizeQuestion(question);
    };
    audio.addEventListener('error', fallBackToSynthesis, { once: true });
    audio.play().catch(fallBackToSynthesis);
  }

  function playAnswerRequiredPrompt() {
    const hindi = language === 2;
    const message = hindi
      ? 'कृपया आगे बढ़ने से पहले एक स्टार रेटिंग चुनें।'
      : 'Please select a star rating before continuing.';
    showError(message);
    stopPlayback();
    const audioLanguage = hindi ? 'hi' : 'en';
    const audio = new Audio(`/ui/assets/audio/opd-demo/${audioLanguage}/select-rating.mp3`);
    activeAudio = audio;
    const fallback = () => {
      if (!('speechSynthesis' in window)) return;
      const speech = new SpeechSynthesisUtterance(message);
      speech.lang = hindi ? 'hi-IN' : 'en-IN';
      window.speechSynthesis.speak(speech);
    };
    audio.addEventListener('error', fallback, { once: true });
    audio.play().catch(fallback);
  }

  function confirmSelection(option, question) {
    stopPlayback();
    const version = playbackVersion;
    if (isOpdRating(question)) {
      const prefix = currentIndex === 0 ? 'first-' : '';
      const audioLanguage = language === 2 ? 'hi' : 'en';
      const audio = new Audio(`/ui/assets/audio/opd-demo/${audioLanguage}/${prefix}rating-${option.value || (question.options || []).indexOf(option) + 1}.mp3`);
      activeAudio = audio;
      scheduleOpdAudioCues(audio, currentIndex === 0, true);
      audio.addEventListener('ended', () => { if (activeAudio === audio) activeAudio = null; }, { once: true });
      audio.addEventListener('error', () => { if (version === playbackVersion) synthesizeQuestion(question); }, { once: true });
      audio.play().catch(() => { if (version === playbackVersion) synthesizeQuestion(question); });
      return;
    }
    const selectedText = String(option.text || '');
    const generatedMessage = language === 2
      ? `आपने ${selectedText} चुना है। अगर यह सही है, तो आगे बढ़ें। बदलना हो तो दूसरा बटन दबाएं।`
      : `You selected ${selectedText}. If this is correct, continue. To change it, press another button.`;
    const speakFallback = () => {
      if (!('speechSynthesis' in window)) return;
      window.speechSynthesis.cancel();
      const speech = new SpeechSynthesisUtterance(option.selection_voice_script || generatedMessage);
      speech.lang = language === 2 ? 'hi-IN' : 'en-IN';
      window.speechSynthesis.speak(speech);
    };
    const voicePath = String(option.selection_voice_path || '').replace(/^\/+/, '');
    if (!voicePath) {
      speakFallback();
      return;
    }
    const audio = new Audio(`/${voicePath}`);
    activeAudio = audio;
    audio.addEventListener('ended', () => { if (activeAudio === audio) activeAudio = null; }, { once: true });
    let fellBack = false;
    const fallBackToSynthesis = () => {
      if (fellBack) return;
      fellBack = true;
      speakFallback();
    };
    audio.addEventListener('error', fallBackToSynthesis, { once: true });
    audio.play().catch(fallBackToSynthesis);
  }

  function currentPosition() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Location is unavailable on this device.'));
        return;
      }
      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
      });
    });
  }

  function locationStatus(location) {
    const status = document.createElement('div');
    status.className = `ab-location-status${location.within_radius ? '' : ' ab-location-denied'}`;
    const heading = document.createElement('strong');
    heading.textContent = location.within_radius ? 'Facility location verified' : 'Outside facility premises';
    const detail = document.createElement('span');
    detail.textContent = `You are approximately ${location.distance_meters} metres from the facility. Allowed radius: ${location.allowed_radius_meters} metres.`;
    status.append(heading, detail);
    return status;
  }

  function playOpdLanguagePrompt() {
    stopPlayback();
    const version = playbackVersion;
    let fallbackUsed = false;
    const speechFallback = () => {
      if (fallbackUsed || version !== playbackVersion || !('speechSynthesis' in window)) return;
      fallbackUsed = true;
      const hindi = new SpeechSynthesisUtterance('कृपया अपनी भाषा चुनें। हिंदी के लिए हरा बटन दबाएं। दोबारा सुनने के लिए स्पीकर बटन दबाएं।');
      hindi.lang = 'hi-IN';
      hindi.onend = () => {
        if (version !== playbackVersion) return;
        const english = new SpeechSynthesisUtterance('For English, press the orange button. To listen again, press the speaker button.');
        english.lang = 'en-IN';
        window.speechSynthesis.speak(english);
      };
      window.speechSynthesis.speak(hindi);
    };
    const englishAudio = new Audio('/ui/assets/audio/opd-demo/hi/language-select-en.mp3');
    const playEnglish = () => {
      if (version !== playbackVersion) return;
      activeAudio = englishAudio;
      englishAudio.addEventListener('ended', () => { if (activeAudio === englishAudio) activeAudio = null; }, { once: true });
      englishAudio.addEventListener('error', speechFallback, { once: true });
      englishAudio.play().catch(speechFallback);
    };
    const hindiAudio = new Audio('/ui/assets/audio/opd-demo/hi/language-select-hi.mp3');
    activeAudio = hindiAudio;
    hindiAudio.addEventListener('ended', playEnglish, { once: true });
    hindiAudio.addEventListener('error', speechFallback, { once: true });
    hindiAudio.play().catch(speechFallback);
  }

  function renderLanguageChoice(location) {
    clearError();
    const view = document.createElement('section');
    view.className = 'ab-language-view';
    view.appendChild(brand(true));
    view.appendChild(locationStatus(location));

    if (!location.within_radius) {
      root.replaceChildren(view);
      return;
    }

    const actions = document.createElement('div');
    actions.className = 'ab-language-actions';
    [[1, 'English'], [2, '\u0939\u093f\u0928\u094d\u0926\u0940']].forEach(([code, label]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'ab-language-button';
      if (isOpdSurvey()) button.classList.add(code === 2 ? 'ab-language-button--opd-hindi' : 'ab-language-button--opd-english');
      button.lang = code === 2 ? 'hi' : 'en';
      button.textContent = label;
      button.addEventListener('click', async () => {
        stopPlayback();
        language = code;
        // Start the first OPD prompt within the language-button tap. Mobile
        // browsers otherwise treat playback after the asynchronous API request
        // as autoplay and can block it silently.
        if (isOpdSurvey()) {
          const audioLanguage = code === 2 ? 'hi' : 'en';
          const audio = new Audio(`/ui/assets/audio/opd-demo/${audioLanguage}/Q1.mp3?v=rating-guide-2`);
          activeAudio = audio;
          scheduleOpdAudioCues(audio, true, false);
          audio.addEventListener('ended', () => { if (activeAudio === audio) activeAudio = null; }, { once: true });
          audio.play().catch(() => { if (activeAudio === audio) activeAudio = null; });
          await loadQuestions(false);
          return;
        }
        await loadQuestions(true);
      });
      actions.appendChild(button);
    });
    view.appendChild(actions);
    if (isOpdSurvey()) {
      const replay = document.createElement('button');
      replay.type = 'button';
      replay.className = 'ab-language-read-aloud';
      replay.textContent = '🔊 भाषा सुनें / Listen';
      replay.addEventListener('click', playOpdLanguagePrompt);
      view.appendChild(replay);
    }
    root.replaceChildren(view);
    if (isOpdSurvey()) playOpdLanguagePrompt();
  }

  async function loadQuestions(autoPlayFirstQuestion = false) {
    clearError();
    root.textContent = 'Loading questions\u2026';
    try {
      const data = await request(apiUrl('questions.php', { ref: reference, lang: language }));
      questions = data.questions || [];
      surveyIdentity = {
        survey_code: data.survey_code || '',
        survey_version: data.survey_version || '',
        survey_schema_hash: data.survey_schema_hash || ''
      };
      buttonLabels = { ...buttonLabels, ...(data.buttons || {}) };
      currentIndex = 0;
      if (!questions.length) {
        throw new Error('No survey questions are available.');
      }
      renderQuestion(autoPlayFirstQuestion && isOpdSurvey());
    } catch (error) {
      showError(error.message || 'Unable to load questions.');
      root.textContent = '';
    }
  }

  function renderQuestion(autoPlayQuestion = false) {
    clearError();
    const question = questions[currentIndex];
    const view = document.createElement('section');
    view.className = 'ab-survey-view';
    if (isOpdRating(question)) view.classList.add('ab-survey-view--opd-stars');
    view.appendChild(brand(false));
    if (!isOpdRating(question)) view.appendChild(questionIcon(question));

    const title = document.createElement('h1');
    title.className = 'ab-survey-question';
    title.textContent = question.ques;

    const readAloud = document.createElement('button');
    readAloud.type = 'button';
    readAloud.className = 'ab-survey-read-aloud';
    readAloud.setAttribute('aria-label', language === 2 ? 'प्रश्न सुनें' : 'Listen to question');
    readAloud.title = language === 2 ? 'प्रश्न सुनें' : 'Listen to question';
    readAloud.textContent = '🔊';
    readAloud.addEventListener('click', () => speakQuestion(question));

    const questionCard = document.createElement('div');
    questionCard.className = 'ab-survey-question-card';
    questionCard.append(title, readAloud);

    const options = document.createElement('fieldset');
    options.className = 'ab-survey-options';
    if (isOpdRating(question)) options.classList.add('ab-opd-stars');
    const legend = document.createElement('legend');
    legend.className = 'visually-hidden';
    legend.textContent = question.ques;
    options.appendChild(legend);
    if (isOpdRating(question)) {
      const ratingTitle = document.createElement('p');
      ratingTitle.className = 'ab-opd-rating-title';
      ratingTitle.textContent = language === 2 ? 'रेटिंग देने के लिए स्टार पर दबाएं' : 'Tap a star to give your rating';
      options.appendChild(ratingTitle);
    }

    const displayOptions = isOpdRating(question)
      ? (question.options || []).slice(0, 5).reverse()
      : (question.options || []);
    displayOptions.forEach((option, index) => {
      const label = document.createElement('label');
      label.className = 'ab-survey-option';
      if (isOpdRating(question)) label.classList.add('ab-survey-star-option');
      const colorIndex = optionColorIndex(question, option, index);
      if (colorIndex) {
        label.classList.add(`ab-survey-option--rating-${colorIndex}`);
      }
      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = `q_${question.qn}`;
      const configuredValue = Number(option.value);
      const optionPosition = (question.options || []).indexOf(option) + 1;
      radio.value = String(Number.isInteger(configuredValue) ? configuredValue : optionPosition);
      radio.checked = String(answers[question.qn] || '') === radio.value;
      radio.addEventListener('change', () => {
        answers[question.qn] = radio.value;
        confirmSelection(option, question);
      });
      const text = document.createElement('span');
      text.textContent = isOpdRating(question) ? '★' : option.text;
      label.append(radio, text);
      options.appendChild(label);
    });
    if (isOpdRating(question)) {
      const scale = document.createElement('p');
      scale.className = 'ab-opd-rating-scale';
      scale.textContent = language === 2
        ? '1 ★ बहुत बुरा · 2 ★ सामान्य · 3 ★ अच्छा · 4 ★ बहुत अच्छा · 5 ★ उत्कृष्ट'
        : '1 ★ Very Poor · 2 ★ Average · 3 ★ Good · 4 ★ Very Good · 5 ★ Excellent';
      options.appendChild(scale);
    }

    const navigation = document.createElement('nav');
    navigation.className = 'ab-survey-nav';
    navigation.setAttribute('aria-label', 'Survey navigation');

    const previous = document.createElement('button');
    previous.type = 'button';
    previous.textContent = buttonLabels[2] || 'Previous';
    previous.hidden = currentIndex === 0;
    previous.addEventListener('click', () => {
      currentIndex -= 1;
      renderQuestion(true);
    });

    const step = document.createElement('p');
    step.className = 'ab-survey-step';
    step.textContent = `${currentIndex + 1} / ${questions.length}`;

    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = currentIndex === questions.length - 1
      ? (buttonLabels[4] || 'Submit')
      : (buttonLabels[3] || 'Next');
    next.addEventListener('click', async () => {
      if (!answers[question.qn]) {
        if (isOpdRating(question)) {
          playAnswerRequiredPrompt();
        } else {
          showError(language === 2 ? 'कृपया आगे बढ़ने से पहले एक उत्तर चुनें।' : 'Please select an answer before continuing.');
        }
        return;
      }
      if (currentIndex < questions.length - 1) {
        currentIndex += 1;
        renderQuestion(true);
        return;
      }
      await submitSurvey(next);
    });

    navigation.append(previous, step, next);
    if (isOpdRating(question)) {
      const surveyCard = document.createElement('div');
      surveyCard.className = 'ab-opd-survey-card';
      surveyCard.append(questionCard, options);
      view.append(surveyCard, navigation);
    } else {
      view.append(questionCard, options, navigation);
    }
    root.replaceChildren(view);
    if (autoPlayQuestion && isOpdRating(question)) {
      speakQuestion(question);
    }
  }

  async function submitSurvey(button) {
    clearError();
    button.disabled = true;
    button.textContent = 'Submitting\u2026';
    try {
      if (context.geo_required && !verifiedLocation) {
        throw new Error('Facility location has not been verified. Please scan the QR code again.');
      }
      const locationPayload = context.geo_required ? {
        latitude: verifiedLocation.latitude,
        longitude: verifiedLocation.longitude,
      } : {};
      const submitted = await request(apiUrl('submit.php'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ref: reference,
          survey_code: surveyIdentity?.survey_code || '',
          survey_version: surveyIdentity?.survey_version || '',
          survey_schema_hash: surveyIdentity?.survey_schema_hash || '',
          lang: language,
          device_id: deviceId(),
          ...locationPayload,
          answers,
        }),
      });

      const success = document.createElement('section');
      success.className = 'ab-survey-success';
      const thankYou = submitted.thank_you || {};
      if (thankYou.icon) {
        const icon = document.createElement('img');
        icon.className = 'ab-survey-thank-you-icon';
        icon.alt = '';
        icon.setAttribute('aria-hidden', 'true');
        icon.src = `/api/${String(thankYou.icon).replace(/^\/+/, '')}`;
        icon.addEventListener('error', () => { icon.hidden = true; }, { once: true });
        success.appendChild(icon);
      }
      const heading = document.createElement('h1');
      heading.textContent = 'Thank you';
      const message = document.createElement('p');
      message.textContent = thankYou.message || 'Your anonymous feedback has been submitted successfully.';
      success.append(heading, message);
      root.replaceChildren(success);
    } catch (error) {
      const denied = error && error.code === 1;
      showError(denied ? 'Location permission is required to submit feedback from the facility premises.' : (error.message || 'Unable to submit feedback.'));
      button.disabled = false;
      button.textContent = buttonLabels[4] || 'Submit';
    }
  }

  async function start() {
    if (!/^\d{6,20}_\d{1,2}$/.test(reference)) {
      showError('This QR survey link is invalid.');
      root.textContent = '';
      return;
    }
    try {
      context = await request(apiUrl('resolve.php', { ref: reference }));
      if (!context.geo_required) {
        renderLanguageChoice({ within_radius: true, distance_meters: 0, allowed_radius_meters: 0 });
        return;
      }
      root.textContent = 'Checking your distance from the facility\u2026';
      const position = await currentPosition();
      verifiedLocation = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      };
      const location = await request(apiUrl('location.php'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ ref: reference, ...verifiedLocation }),
      });
      renderLanguageChoice(location);
    } catch (error) {
      const denied = error && error.code === 1;
      showError(denied
        ? 'Please allow location access to verify your distance from the facility.'
        : (error.message || 'This survey is unavailable.'));
      root.textContent = '';
    }
  }

  start();
}());

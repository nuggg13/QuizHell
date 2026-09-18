import { AudioEffects } from './audio.js';

const { category, sounds } = JSON.parse(document.getElementById('result-data').textContent);
const audio = new AudioEffects(sounds);
const button = document.getElementById('result-reaction');
// Play here so navigation from the quiz cannot cut off a custom result clip.
// If autoplay is blocked, the existing reaction button retries after a gesture.
audio.result(category);
button.addEventListener('click', () => audio.result(category));

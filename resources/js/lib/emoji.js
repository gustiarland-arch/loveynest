// Emoji for the chat picker. Plain Unicode, no images and no library; every entry is Emoji 13 or older
// so it shows on phones that are a few years old.
export const emojiGroups = [
    { key: 'smileys', label: 'Wajah', icon: '😀', items: '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇 🙂 🙃 😉 😌 😍 🥰 😘 😗 😙 😚 😋 😛 😝 😜 🤪 🤨 🧐 🤓 😎 🤩 🥳 😏 😒 😞 😔 😟 😕 🙁 ☹️ 😣 😖 😫 😩 🥺 😢 😭 😤 😠 😡 🤬 🤯 😳 🥵 🥶 😱 😨 😰 😥 😓 🤗 🤔 🤭 🤫 🤥 😶 😐 😑 😬 🙄 😯 😦 😧 😮 😲 🥱 😴 🤤 😪 😵 🤐 🥴 🤢 🤮 🤧 😷 🤒 🤕 🤑 🤠 😈 👿 🤡 💩 👻 💀 👽 🤖 😺 😸 😹 😻 😼 😽 🙀 😿 😾'.split(' ') },
    { key: 'hearts', label: 'Cinta', icon: '❤️', items: '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 💟 💌 💋 😍 🥰 😘 😻 💑 💏 👩‍❤️‍👨 👨‍❤️‍👨 👩‍❤️‍👩 🌹 🥀 🌷 🌸 💐 🌺 🌻 🌼 💍 💎 🎁 🧸 🕊️ ✨ 💫 ⭐ 🌟'.split(' ') },
    { key: 'gestures', label: 'Tangan', icon: '👍', items: '👍 👎 👌 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 🤝 🙏 ✍️ 💪 👏 🙌 👐 🤲 🤗 💅 🙋 🙆 🙅 🤷 🤦 💁 🙇 🏃 💃 🕺 🚶 👀 👁️ 👅 👄 🧠'.split(' ') },
    { key: 'people', label: 'Orang', icon: '🧑', items: '👶 🧒 👦 👧 🧑 👨 👩 🧓 👴 👵 👱 🧔 👸 🤴 🤵 👰 🤰 🤱 👼 🎅 🤶 🦸 🦹 🧙 🧚 🧜 🧞 👫 👭 👬 👪 👨‍👩‍👧‍👦 👨‍👩‍👧 🧑‍🤝‍🧑 💆 💇 🛌'.split(' ') },
    { key: 'animals', label: 'Hewan', icon: '🐶', items: '🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🙈 🙉 🙊 🐔 🐧 🐦 🐤 🦆 🦅 🦉 🦇 🐺 🐗 🐴 🦄 🐝 🐛 🦋 🐌 🐞 🐜 🐢 🐍 🐙 🦑 🦐 🦀 🐡 🐠 🐟 🐬 🐳 🐋 🦈 🐊 🐘 🦒 🦓 🐕 🐈 🐇 🐿️ 🦔 🐾 🦢 🦜 🐓'.split(' ') },
    { key: 'nature', label: 'Alam', icon: '🌿', items: '🌱 🌿 ☘️ 🍀 🍁 🍂 🍃 🌵 🌴 🌳 🌲 🌈 ☀️ 🌤️ ⛅ ☁️ 🌧️ ⛈️ 🌩️ ❄️ ☃️ ⛄ 🌙 🌛 🌜 🌝 🌞 ⭐ 🌟 ✨ ⚡ 🔥 💧 💦 🌊 🌍 🌏 🪐 🌸 🌺 🌻 🌼 🌷 🌹 🥀'.split(' ') },
    { key: 'food', label: 'Makanan', icon: '🍕', items: '🍎 🍐 🍊 🍋 🍌 🍉 🍇 🍓 🍒 🍑 🥭 🍍 🥥 🥝 🍅 🥑 🍆 🥕 🌽 🌶️ 🥔 🍞 🥐 🥖 🧀 🥚 🍳 🥞 🍗 🍖 🌭 🍔 🍟 🍕 🥪 🌮 🌯 🥗 🍝 🍜 🍲 🍛 🍣 🍱 🥟 🍤 🍙 🍚 🍘 🍥 🍦 🍧 🍨 🍩 🍪 🎂 🍰 🧁 🍫 🍬 🍭 🍮 🍯 🍼 🥛 ☕ 🍵 🧃 🥤 🍺 🍻 🥂 🍷 🍸 🍹 🍾'.split(' ') },
    { key: 'activity', label: 'Aktivitas', icon: '🎮', items: '⚽ 🏀 🏈 ⚾ 🎾 🏐 🏓 🏸 🎱 🎯 🎳 🎮 🕹️ 🎲 🧩 🎨 🎬 🎤 🎧 🎸 🎹 🥁 🎻 🎭 🎪 🎠 🎡 🎢 🏆 🥇 🥈 🥉 🎁 🎈 🎉 🎊 🎀 🏖️ 🏕️ ⛺ 🚴 🏊 🧘 🎣 🛹'.split(' ') },
    { key: 'travel', label: 'Tempat', icon: '✈️', items: '🚗 🚕 🚌 🚎 🏍️ 🛵 🚲 🚂 ✈️ 🚀 🚁 ⛵ 🚢 🏠 🏡 🏢 🏨 🏥 ⛪ 🏖️ 🏝️ ⛰️ 🏔️ 🌋 🗼 🗽 🎡 🌆 🌃 🌉 🌅 🌄 🗺️ 🧭 ⛽ 🚦'.split(' ') },
    { key: 'objects', label: 'Benda', icon: '📱', items: '📱 💻 ⌨️ 🖥️ 📷 📸 🎥 📞 ☎️ ⏰ ⌚ 💡 🔦 🔑 🔒 🔓 🛏️ 🛋️ 🚿 🛁 🧸 👗 👠 👑 👜 🕶️ 🌂 ☂️ 📚 📖 ✏️ 📝 📌 📎 ✂️ 💰 💳 🛒 🎒 🧳 📦 💊 🧴 🪞 🕯️ 🧹'.split(' ') },
    { key: 'symbols', label: 'Simbol', icon: '✅', items: '✅ ❌ ❓ ❗ ‼️ 💯 🔔 🔕 🎵 🎶 💤 💢 💥 💫 💨 ♾️ ✔️ ➕ ➖ 💭 🗯️ 💬 🔝 🆗 🆒 🆕 🔞 ⚠️ ♻️ 🔴 🟠 🟡 🟢 🔵 🟣 ⚪ ⚫ ▶️ ⏸️ 🔁 🔀 ➡️ ⬅️ ⬆️ ⬇️'.split(' ') },
];

export const quickReactions = ['❤️', '😂', '😮', '😢', '👍', '🔥'];

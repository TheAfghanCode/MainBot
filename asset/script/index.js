// window.onload = () => {
const tg = window.Telegram.WebApp;
tg.ready();
tg.expand();

const user_id = tg.initDataUnsafe?.user?.id;

if (user_id) {
}
//   };

function sendMessage(messageText) {
  fetch("https://mainbot-g94g.onrender.com/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      user_id: user_id,
      message: messageText,
    }),
  });
}

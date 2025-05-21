function updateTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        month: 'numeric',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
        timeZone: 'Asia/Yangon'
    };
    const timeString = now.toLocaleString('en-US', options);
    document.getElementById('real-time').textContent = timeString;
}
setInterval(updateTime, 1000);
updateTime();
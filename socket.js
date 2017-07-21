window.onload = function () {

    var url = 'ws://127.0.0.1:8000'
    var socket = new WebSocket(url)

    socket.onopen = function () {
        if (socket.readyState == 1) {
            socket.send('test');
        }
    }

    socket.onclose = function () {
        socket = false;
        console.log('close')
    }

    socket.onmessage = function (result) {
        console.log(result)
    }

    socket.onerror = function (msg) {
        console.log(msg)
    }
}

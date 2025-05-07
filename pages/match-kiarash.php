<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
    <title>6x7 Dooz (Connect Four)</title>
    <style>

        body {
            height: 100vh;
            background-color: #131720;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
            text-align: center;
            overflow: hidden;
        }
        .main-container{
            width: 100%;
            margin: 0;
            padding: 0;
            display: flex; /* Use flexbox for layout */
            flex-direction: column; /* Stack items vertically */
            justify-content: center; /* Center items vertically */
            align-items: center; /* Center items horizontally */
            height: calc(100vh - 30vh);
        }
        .game-container {
            margin-top: 0;
            /* background-color: #333; */
            padding: 15px;
            border-radius: 10px 10px 0 0 ;
            background-color: #fcba03;
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            overflow: hidden;
            gap: 10px;
            width: 75%;
            max-width: 500px;
            aspect-ratio: 7 / 6;
            position: relative;
            height: max-content;
            min-height: 0;
        }

        .cell {
            width: 100%;
            padding-top: 100%;
            background-color: white;
            border-radius: 50%;
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .piece {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            position: absolute;
            top: -700%; /* Start above the grid cell */
            left: 50%;
            transform: translateX(-50%);
            transition: top 0.7s ease-in; /* Smooth fall animation */
        }

        .player1 {
            background-color: red;
        }

        .player2 {
            background-color: blue;
        }

        .button-container {
            /*margin-top: 30px;*/
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            max-width: 500px;
            background-color: #fcba03;
            width: 75%;
            border-radius: 10px;
            padding: 10px 30px;
        }

        .btn {
            cursor: pointer;
            border: none;
            background-color: #333333;
            color: white;
            font-size: 1.2rem;
            aspect-ratio: 1 / 1; /* Ensures the button remains a square */
            border-radius: 50%; /* Makes it a circle */
            height: auto; /* Adjust height automatically based on width */

            display: flex;
            justify-content: center;
            align-items: center;
        }


        h1 {
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 10px;
            color: white;
        }

        h2 {
            margin-top: 20px;
            font-size: 1.5rem;
            color: white;
            text-align: center;
        }

        #status {
            color: white;
        }

        @media (max-width: 400px) {
            h1 {
                font-size: 1.5rem;
            }

            button {
                font-size: 1rem;
            }

            h2 {
                font-size: 1.2rem;
            }
        }
        @supports (-webkit-touch-callout: none) {
            .game-container {
                /*height: calc(100vh - 100px); !* Adjust according to the available space *!*/
                padding-bottom: 30px;
            }
        }
        .header {
            margin-right: auto;
            margin-left: auto;
            display: flex;
            width: 90%;
            max-width: 500px;
            text-align: center;
            vertical-align: middle;
            justify-content: center;
            align-items: center;
            max-height: 100% ;
            /*margin-bottom: 30px;*/
        }
        .me{
            display: flex;
            vertical-align: middle;
            justify-content: center;
            align-items: center;
            background-color: #fcba03;
            margin-right: -10px;
            width: 42.5%;
            border-radius: 5px 0 0 0 ;
            height: 35px;
            border-left: red 6px solid;
        }
        .circle{
            display: flex;
            background-color: #fcba03;;
            border-radius: 50%;
            cursor: pointer;
            border: none;
            aspect-ratio: 1 / 1;
            width: 15%;
            height: auto;
            color: black;
            vertical-align: middle;
            justify-content: center;
            align-items: center;
            font-size: 1.8rem;
            font-weight: bold;
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
            z-index: 5;
        }
        .enemy{
            margin-left: -10px;
            display: flex;
            vertical-align: middle;
            justify-content: center;
            align-items: center;
            background-color: #fcba03;
            width: 42.5%;
            border-radius:  0 5px 0 0 ;
            height: 35px;
            border-right: blue 6px solid;

            /*font-weight: bold;*/
        }
        .jam {
            margin-right: auto;
            margin-left: auto;
            width: 90%;
            max-width: 500px;
            max-height: 100%;
            margin-bottom: 30px;
            background-color: red;
            margin-top: -20px;
        }
        .jam-me {
            float: left;
            background-color: #fcba03;
            border-radius: 0 0 5px  5px ;
            border-left: red 6px solid;
            margin-right: -10px;
            transform: translateX(4px);
            padding: 5px;
        }
        .jam-enemy{

            float: right;
            background-color: #fcba03;
            border-radius:  0 0 5px 5px ;
            border-right: blue 6px solid;
            margin-left: -10px;
            transform: translateX(-4px);
            padding: 5px;

        }
    </style>
</head>
<body>


<div style="text-align: center!important;width: 100%!important;">
    <div class="header">
        <div class="me">Elnaz</div>
        <div class="circle">vs</div>
        <div class="enemy">Shahroz</div>
    </div>
    <div class="jam">
        <div>
            <div class="jam-me">221🏆</div>
            <div class="jam-enemy">1231🏆</div>
        </div>
    </div>
</div>


<div class="main-container">
    <div class="game-container" id="gameContainer">
        <!--divrid will be dynamically generated here -->
    </div>


    <div class="button-container">
        <div class="btn" onclick="dropPiece(0)">1</div>
        <div class="btn" onclick="dropPiece(1)">2</div>
        <div class="btn" onclick="dropPiece(2)">3</div>
        <div class="btn" onclick="dropPiece(3)">4</div>
        <div class="btn" onclick="dropPiece(4)">5</div>
        <div class="btn" onclick="dropPiece(5)">6</div>
        <div class="btn" onclick="dropPiece(6)">7</div>
    </div>
</div>

<script>
    const ROWS = 6;
    const COLS = 7;
    let currentPlayer = 1;
    let gameOver = false;
    let board = Array.from({ length: ROWS }, () => Array(COLS).fill(0));

    // Create the game board dynamically
    const gameContainer = document.getElementById('gameContainer');
    function renderBoard() {
        gameContainer.innerHTML = '';
        for (let r = 0; r < ROWS; r++) {
            for (let c = 0; c < COLS; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell');
                gameContainer.appendChild(cell);
            }
        }
    }

    // Smoothly move the piece to its final position
    function animateDrop(col, targetRow, playerClass) {
        const cell = gameContainer.children[targetRow * COLS + col];
        const piece = document.createElement('div');
        piece.classList.add('piece', playerClass);
        cell.appendChild(piece);

        // Set initial top position (start high above the grid)
        piece.style.top = `-${(targetRow + 2) * 100}%`;

        // Calculate the final top position based on the target row
        setTimeout(() => {
            piece.style.top = '0';  // Drop to the final position
        }, 50);  // A slight delay to ensure animation is visible
    }



    // Check for a win (horizontal, vertical, diagonal)
    function checkWin(player) {
        // Horizontal check
        for (let r = 0; r < ROWS; r++) {
            for (let c = 0; c < COLS - 3; c++) {
                if (board[r][c] === player && board[r][c + 1] === player && board[r][c + 2] === player && board[r][c + 3] === player) {
                    return true;
                }
            }
        }

        // Vertical check
        for (let r = 0; r < ROWS - 3; r++) {
            for (let c = 0; c < COLS; c++) {
                if (board[r][c] === player && board[r + 1][c] === player && board[r + 2][c] === player && board[r + 3][c] === player) {
                    return true;
                }
            }
        }

        // Diagonal (top-left to bottom-right)
        for (let r = 0; r < ROWS - 3; r++) {
            for (let c = 0; c < COLS - 3; c++) {
                if (board[r][c] === player && board[r + 1][c + 1] === player && board[r + 2][c + 2] === player && board[r + 3][c + 3] === player) {
                    return true;
                }
            }
        }

        // Diagonal (bottom-left to top-right)
        for (let r = 3; r < ROWS; r++) {
            for (let c = 0; c < COLS - 3; c++) {
                if (board[r][c] === player && board[r - 1][c + 1] === player && board[r - 2][c + 2] === player && board[r - 3][c + 3] === player) {
                    return true;
                }
            }
        }

        return false;
    }

    // Drop a piece into a column
    function dropPiece(col) {
        if (gameOver) {
            alert("Game is over. Please refresh to start a new game.");
            return;
        }

        for (let row = ROWS - 1; row >= 0; row--) {
            if (board[row][col] === 0) {
                board[row][col] = currentPlayer;
                const playerClass = currentPlayer === 1 ? 'player1' : 'player2';
                animateDrop(col, row, playerClass);

                // Check if the current player wins
                if (checkWin(currentPlayer)) {
                    document.getElementById('status').innerText = `Player ${currentPlayer} Wins!`;
                    gameOver = true;
                    return;
                }

                // Switch to the next player
                currentPlayer = currentPlayer === 1 ? 2 : 1;
                document.getElementById('currentPlayer').innerText = currentPlayer;
                return;
            }
        }
        alert("Column is full!");
    }

    // Initialize the board
    renderBoard();
</script>
<script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>
<script>
    // Connect to the WebSocket server
    const socket = io('wss://socket.bemola.site:8443', {
        transports: ['websocket'], // Force WebSocket transport only
        secure: true
    });

    // Log connection status
    socket.on('connect', () => {
        console.log('Connected to server');
    });

    // Log disconnection status
    socket.on('disconnect', () => {
        console.log('Disconnected from server');
    });

    // Handle incoming messages from the server
    socket.on('message', (data) => {
        console.log('Received message from server:', data);
    });

    // Send a message to the server
    socket.emit('message', 'Hello from the client!');
</script>

</body>
</html>

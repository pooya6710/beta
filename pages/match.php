<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Application\Model\DB;

require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/application/Model/Model.php');
require_once(dirname(__DIR__) . '/application/Model/DB.php');
require_once(dirname(__DIR__) . '/application/Model/DB.php');
$dotenv = \Dotenv\Dotenv::createImmutable((dirname(__DIR__) . '/'));
$dotenv->safeLoad();
function rm($input)
{
    // Use preg_replace to remove all characters except letters and numbers
    $cleaned = preg_replace('/[^A-Za-z0-9]/', '', $input);
    return $cleaned;
}

$match = null;
if ($_GET['player'] != '1' && $_GET['player'] != '2') {
    exit('Wrong player');
}
$q = "SELECT matches.*, u1.username AS player1_name, u2.username AS player2_name , u1.id AS player1_id ,u2.id AS player2_id 
      FROM matches 
      JOIN users u1 ON matches.player1 = u1.telegram_id 
      JOIN users u2 ON matches.player2 = u2.telegram_id 
      WHERE matches.player" . $_GET['player'] . "_hash = ?";

if ($_GET['player'] == '1') {
    $match = DB::rawQuery($q, [rm($_GET['hash'])]);
} //if ($_GET['player']=='1') $match = DB::rawQuery('SELECT * FROM matches WHERE player1_hash = ?;', [rm($_GET['hash'])]);
elseif ($_GET['player'] == '2') $match = DB::rawQuery($q, [rm($_GET['hash'])]);
else exit('none');
if (!$match) exit();
$match = $match[0];
$host = $_SERVER['HTTP_HOST'];
$x = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
array_pop($x);
$x = implode('/', $x);
$mainDomain = preg_replace('/^www\./', '', $host);
$mainDomain = $mainDomain . '/' . $x;
if ($match['status'] == 'finished') {
    require_once (__DIR__) . '/finish.php';
}
$user1_id = DB::table('users')->where('telegram_id' , $match['player1'])->select('id')->first()['id'];

$user2_id = DB::table('users')->where('telegram_id' , $match['player2'])->select('id')->first()['id'];


$now = new DateTime();

$createdAt = new DateTime(DB::table('users')->where('telegram_id' , $match['player1'])->select('created_at')->first()['created_at']);
$diffInSeconds = $now->getTimestamp() - $createdAt->getTimestamp() ;
$user1_activity = floor($diffInSeconds / 86400);

$createdAt = new DateTime(DB::table('users')->where('telegram_id' , $match['player2'])->select('created_at')->first()['created_at']);
$diffInSeconds = $now->getTimestamp() - $createdAt->getTimestamp() ;
$user2_activity = floor($diffInSeconds / 86400);
unset($createdAt ,$diffInSeconds , $now);

$user1 = DB::table('users_extra')->where('user_id' , $user1_id)->select('win_rate,matches,cups')->first();
$user2 = DB::table('users_extra')->where('user_id' , $user2_id)->select('win_rate,matches,cups')->first();

$user1_winRate_rank = DB::rawQuery('SELECT user_id, winRate, user_rank FROM ( SELECT user_id, (wins / matches) * 100 AS winRate, RANK() OVER (ORDER BY (wins / matches) DESC) AS user_rank FROM users_extra WHERE matches > 0 ) AS ranked_users WHERE user_id = ?;', [$user1_id]);
if ($user1_winRate_rank == []) {
    $user1_winRate_rank = 0;
}
else{
    $user1_winRate_rank = $user1_winRate_rank[0]['user_rank'];
}
$user2_winRate_rank = DB::rawQuery('SELECT user_id, winRate, user_rank FROM ( SELECT user_id, (wins / matches) * 100 AS winRate, RANK() OVER (ORDER BY (wins / matches) DESC) AS user_rank FROM users_extra WHERE matches > 0 ) AS ranked_users WHERE user_id = ?;', [$user2_id]);
if ($user2_winRate_rank == []) {
    $user2_winRate_rank = 0;
}
else{
    $user2_winRate_rank = $user2_winRate_rank[0]['user_rank'];
}

$user1_total_rank = DB::rawQuery('SELECT user_id, matches, user_rank FROM ( SELECT id, user_id, matches, RANK() OVER (ORDER BY matches DESC) AS user_rank FROM users_extra ) AS ranked_users WHERE user_id = ?;', [$user1_id])[0]['user_rank'];
$user2_total_rank = DB::rawQuery('SELECT user_id, matches, user_rank FROM ( SELECT id, user_id, matches, RANK() OVER (ORDER BY matches DESC) AS user_rank FROM users_extra ) AS ranked_users WHERE user_id = ?;', [$user2_id])[0]['user_rank'];

$user_data = [
    1 => [
        'activity' => $user1_activity ,
        'cups' => $user1['cups'] ,
        'win_rate' => $user1['win_rate'],
        'total' => $user1['matches'],
        'win_rate_rank' => $user1_winRate_rank,
        'total_rank' => $user1_total_rank,

    ] ,
    2 =>[
        'activity' => $user2_activity ,
        'cups' => $user2['cups'],
        'win_rate' => $user2['win_rate'],
        'total' => $user2['matches'],
        'win_rate_rank' => $user2_winRate_rank,
        'total_rank' => $user2_total_rank,

    ]
];
//
$q = "SELECT users_extra.friends from users_extra
      WHERE users_extra.user_id = ? " ;

$friendList = DB::rawQuery($q, [$match['player1_id']])[0];
if ($friendList['friends']) $friendList = json_decode($friendList['friends'], true);
else $friendList = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
    <title>6x7 Dooz (Connect Four)</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
            integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link href="https://<?= $mainDomain ?>/public/match/style.css?code=<?= rand(0, 9999999999999) ?>" rel="stylesheet"/>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
        }
        .reaction-button {
            font-size: 24px;
            padding: 10px 20px;
            cursor: pointer;
        }
        .reaction-popup {
            display: none;
            position: absolute;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background-color: white;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }
        .reaction-popup button {
            font-size: 24px;
            margin: 5px;
            padding: 5px;
            cursor: pointer;
        }
        .reaction-area {
            position: relative;
            width: 100%;
            height: 400px;
            border: 2px solid #ccc;
            margin-top: 20px;
            overflow: hidden;
        }
        .reaction {
            position: absolute;
            font-size: 30px;
            animation: floatUp 2s ease-in-out forwards;
        }
        @keyframes floatUp {
            0% {
                opacity: 1;
                transform: translateY(0);
            }
            100% {
                opacity: 0;
                transform: translateY(-200px);
            }
        }
        /* Cooldown overlay */
        .cooldown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            font-size: 30px;
            color: white;
        }
    </style>
</head>
<body id="reactionArea">
<div id="main-content" style="display: none;">
    <style>
        .disconnected {
            color: black;
            background-image: repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 5px,
                    rgba(0, 0, 0, 0.3) 5px,
                    rgba(0, 0, 0, 0.3) 10px
            );
        }
        .disconnected{
            color: black;
            background-image: repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 5px,
                    rgba(0, 0, 0, 0.3) 5px,
                    rgba(0, 0, 0, 0.3) 10px
            );
        }
        .me,.enemy{
            cursor: pointer;
        }
    </style>
    <div class="header">
            <div class="me disconnected" id="player1-name" ><?= $match['player1_name'] ?: '????' ?></div>

        <div class="circle" id="vs">vs</div>
        <div class="enemy disconnected" id="player2-name"><?= $match['player2_name'] ?: '????' ?></div>

    </div>
<!--    <div class="jam">-->
<!--        <div>-->
<!--            <div class="jam-me disconnected" id="jam-me">--><?php //= $user1['cups'] ?><!--🏆</div>-->
<!--            <div >YOUR TURN</div>-->
<!--            <div class="jam-enemy disconnected" id="jam-enemy">--><?php //= $user2['cups']?><!--🏆</div>-->
<!--        </div>-->
<!--    </div>-->
    <div class="jam">
        <div class="jam-container">
            <div class="jam-me disconnected" id="jam-me"><?= $user1['cups'] ?>🏆</div>
            <div class="jam-turn" id="jam-turn">YOUR TURN</div>
            <div class="jam-enemy disconnected" id="jam-enemy"><?= $user2['cups'] ?>🏆</div>
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
    <style>
        .footer {
            margin-right: auto;
            margin-left: auto;
            display: flex;
            width: 100%;
            max-width: 500px;
            text-align: center;
            vertical-align: middle;
            justify-content: center;
            align-items: center;
            max-height: 100%;
            /*margin-bottom: 30px;*/

            position: fixed;
            bottom: 40px;
            left: 0;
            right: 0;
        }

        .add-friend {
            margin-left: -10px;
            display: flex;
            vertical-align: middle;
            justify-content: center;
            align-items: center;
            background-color: #fcba03;
            width: 40%;
            border-radius: 5px;
            height: 50px;
            cursor: pointer;
            font-family: system-ui;
        }

        .report {
            display: flex;
            vertical-align: middle;
            justify-content: center;
            align-items: center;
            background-color: #fcba03;
            margin-right: -10px;
            width: 40%;
            border-radius: 5px;
            height: 50px;

        }

        .emoji {
            display: flex;
            background-color: #fcba03;;
            border-radius: 50%;
            cursor: pointer;
            border: none;
            aspect-ratio: 1 / 1;
            width: 20%;
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
        .emoji.disabled {
            cursor: not-allowed;
            background-color: #c4c4c4; /* Gray out the background */
            pointer-events: none; /* Prevent any interaction */
            box-shadow: none; /* Remove the shadow to make it look flat */
            color: #7a7a7a; /* Change text color to a lighter gray */
        }
    </style>
    <div class="footer">
        <div class="reaction-popup" id="reactionPopup">

            <button onclick="addReaction('👍', this.id)" id="reaction-1">👍</button>
            <button onclick="addReaction('❤️', this.id)" id="reaction-2">❤️</button>
            <button onclick="addReaction('😂', this.id)" id="reaction-3">😂</button>
            <button onclick="addReaction('🎉', this.id)" id="reaction-4">🎉</button>
            <button onclick="addReaction('👏', this.id)" id="reaction-5">👏</button>
            <button onclick="addReaction('🤔', this.id)" id="reaction-6">🤔</button>
        </div>
        <div class="report">
<!--            <button onclick="getRoomPlayers(roomID)"></button>-->
            <div class="add-friend" onclick="alertUserData('<?= $_GET['player'] ?>')">مشخصات</div>

        </div>
        <div class="emoji" onclick="toggleReactionPopup()">
            👍
        </div>
        <?php if(!in_array($match['player2_id'],$friendList)){ ?>
            <div class="add-friend" onclick="addFriend()">درخواست دوستی</div>
        <?php } else{ ?>
            <div class="add-friend">از قبل دوست هستید</div>
        <?php } ?>
    </div>
</div>
<div id="currentPlayer" style="display: none"><?= $match['turn'] ?></div>
<div id="status" style="display: none">1</div>
<input type="hidden" id="last-update" value='<?php echo($match["updated_at"]); ?>'>

<!-- loader js -->
<!-- Your loader -->
<style>
    .loader {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
        background-color: rgba(0, 0, 0, 0.75); /* dark transparent background */
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
        font-size: 2rem;
    }

    #timer {
        font-size: 1.5rem;
        margin-top: 20px;
    }

</style>
<div id="loader" class="loader">
    <div id="message">loading...</div>
    <div id="timer"></div>
</div>
<script>
    // Function to display and update the countdown timer
    function startCountdown() {
        const timerElement = document.getElementById("timer");
        const updatedTime = new Date(document.getElementById('last-update').value); // Input updated_at datetime
        const targetTime = new Date(updatedTime.getTime() + 15 * 60 * 1000); // 15 minutes later
        const interval = setInterval(() => {
            const now = new Date();
            const remainingTime = targetTime - now;

            if (remainingTime <= 0) {
                clearInterval(interval);
                timerElement.innerHTML = "00:00";
                // Optionally, perform action when the timer reaches 0, like removing loader
            } else {
                const minutes = Math.floor(remainingTime / 1000 / 60);
                const seconds = Math.floor((remainingTime / 1000) % 60);
                timerElement.innerHTML = `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
            }
        }, 1000);
    }

    // Function to show the loader and pause other content
    function showLoader(updatedAt) {
        document.getElementById("loader").style.display = "flex";
        document.getElementById("main-content").style.display = "none"; // Hide other content
        startCountdown(updatedAt); // Start the countdown timer
    }

    // Function to remove the loader and show content
    function removeLoader() {
        document.getElementById("loader").style.display = "none";
        document.getElementById("main-content").style.display = "block"; // Show the content again
    }


</script>
<script src="https://cdn.socket.io/4.5.0/socket.io.min.js"></script>

<style>
    .piece.player2, .piece.player1 {
        top: 0px;
    }
</style>
<script>
    <?php
    if (isset($_GET['telazzzzazazazazaaqwrt'])) {?>
    <?php if ($_GET['telazzzzazazazazaaqwrt'] == 1){ ?>
    params = new URLSearchParams('query_id=AAHca8UyAAAAANxrxTJUK-Zt&user=%7B%22id%22%3A851799004%2C%22first_name%22%3A%22404+NotFound%22%2C%22last_name%22%3A%22%22%2C%22username%22%3A%22E404_notFound%22%2C%22language_code%22%3A%22en%22%2C%22allows_write_to_pm%22%3Atrue%7D&auth_date=1727010975&hash=eccbd244a5595d2032a12d5905bab229600b55013bbfa46a9da19ab2e195fadd');
    <?php }?>
    <?php if ($_GET['telazzzzazazazazaaqwrt'] == 2){ ?>
    params = new URLSearchParams('query_id=AAG2eYZ-AAAAALZ5hn5L3L9q&user=%7B%22id%22%3A2122742198%2C%22first_name%22%3A%22%E2%80%A2+%F0%9D%99%88%22%2C%22last_name%22%3A%22%22%2C%22username%22%3A%22xMhyRx%22%2C%22language_code%22%3A%22en%22%2C%22is_premium%22%3Atrue%2C%22allows_write_to_pm%22%3Atrue%7D&auth_date=1727011569&hash=1c58d787c202c1fb371bbb84900959f6fdd35041dbdaf8944979c3e62d95ce90');
    <?php }?>
    <?php }
    ?>
    if (!window.Telegram.WebApp.initData) {

        // params = 'asassa'
        // if (!window.Telegram.WebApp.initData) alert('ERROR IS CUMMING');
    } else {
        window.Telegram.WebApp.expand();
        params = new URLSearchParams(window.Telegram.WebApp.initData);
    }
    const userDataEncoded = params.get('user');
    const userDataJson = decodeURIComponent(userDataEncoded);
    let user;
    user = JSON.parse(userDataJson);
    const userFullName = user.first_name + user.last_name

    const playerCode = '<?= $_GET["player"] ?>'
    const opponentCode = '<?= $_GET["player"] == 1 ? 2 : 1 ?>'
    const roomID = '<?= $_GET["id"] ?>'
    const playerHash = '<?= $_GET["hash"] ?>'
    const playerUserName1 = '<?= $match["player1_name"] ?>'
    const playerUserName2 = '<?= $match["player2_name"] ?>'
    const oppnentName = opponentCode == 2 ?playerUserName2 :playerUserName1;
    const playerTelegramId1 = '<?= $match["player1"] ?>'
    const playerTelegramId2 = '<?= $match["player2"] ?>'
    opponentTelegramCode = opponentCode==1?playerTelegramId1:playerTelegramId2


    function userOnConnect(player) {
        // alert(player)


        if (player == 1){

            const playerScore = document.getElementById(`jam-me`);
            const playerDiv = document.getElementById(`player1-name`);

            playerDiv.classList.remove('disconnected');
            playerScore.classList.remove('disconnected');

        }else if(player == 2) {
            const playerScore = document.getElementById(`jam-enemy`);
            const playerDiv = document.getElementById(`player2-name`);

            playerDiv.classList.remove('disconnected');
            playerScore.classList.remove('disconnected');
        }



    }

    function userOnDisconnect(player) {
        // alert(player)

        if (player == 1){

            const playerScore = document.getElementById(`jam-me`);
            const playerDiv = document.getElementById(`player1-name`);

            playerDiv.classList.add('disconnected');
            playerScore.classList.add('disconnected');

        }
        else if(player == 2) {


            const playerScore = document.getElementById(`jam-enemy`);
            const playerDiv = document.getElementById(`player2-name`);

            playerDiv.classList.add('disconnected');
            playerScore.classList.add('disconnected');
        }


    }

    function currentPlayerToggle(player) {

        player = `${player}`
        element = document.getElementById('vs')
        if (player == '1') {
            element.style.borderRight  = "none";
            element.style.borderLeft = "4px solid red";
        } else if (player == 2) {
            element.style.borderLeft = "none";
            element.style.borderRight  = "4px solid blue";
        }
        element = document.getElementById('jam-turn')
        if(playerCode == player){
            element.style.display = 'block';
        }else{
            element.style.display = 'none';
        }
    }

    // Connect to the WebSocket server
    const socket = io('wss://socket.bemola.site:8443', {
        transports: ['websocket'], // Force WebSocket transport only
        secure: true
    });
    //
    // const socket = io('wss://socket.bemola.site:8443', {
    //     transports: ['websocket'],  // Use WebSocket only
    //     secure: true,
    //     reconnection: true,         // Enable reconnection
    //     reconnectionAttempts: 5,    // Maximum number of reconnection attempts
    //     reconnectionDelay: 2000,    // Wait 2 seconds between attempts
    //     timeout: 5000               // Connection timeout of 5 seconds
    // });


    socket.on('connect', () => {
        console.log('Connected to server');
        joinRoom(playerCode, userFullName, roomID)
    });
    socket.on('disconnect', () => {
        console.log('DisConnected to server');
    });

    /////////////////////////// EXTRA RECONNECTION ///////////////////////////
    // Log each reconnection attempt
    // socket.on('reconnect_attempt', (attempt) => {
    //     console.log(`Reconnection attempt ${attempt}...`);
    // });
    //
    // // Log any errors that occur during reconnection
    // socket.on('reconnect_error', (error) => {
    //     console.error('Reconnection error:', error);
    // });
    //
    // // If reconnection fails after the configured attempts, prompt the user to reload the page
    // socket.on('reconnect_failed', () => {
    //     console.error('Reconnection failed. Please reload the page.');
    //     // Optionally, display a more user-friendly message in the UI
    //     alert('Unable to reconnect to the server. Please reload the page.');
    // });


    /////////////////////////// EXTRA RECONNECTION ///////////////////////////

    function joinRoom(playerNumber, playerName, room) {
        socket.emit('join_room', {
            player_number: playerNumber,
            player_name: playerName,
            room: room,
            player_hash: playerHash
        });
        console.log('current user joined the room')
    }

    // Event listener for player join notifications
    socket.on('player_joined', (data) => {
        console.log(`player_joined event: Players in room ${data.room}:`);
        console.log(data);
        if (data.room_data[opponentCode]) {
            if (data.room_data[opponentCode]['disconnected'] == false) {
                userOnConnect((opponentCode))
            }
        }
        userOnConnect((data.player))
    });

    // Event listener for player leave notifications
    socket.on('player_left', (data) => {
        console.log(`A player left room ${data.room}. Current players:`);
        console.log(data);
    });

    // Get current players in a room && Event listener for current players in the room
    function getRoomPlayers(room) {
        socket.emit('get_room_players', {room: room, player_hash: playerHash});
    }

    socket.on('room_players', (data) => {
        if (data.error) {
            console.error(data.error);
        } else {
            console.log(`Current players in room ${data.room}:`);
            console.log(data);
        }
    });


    socket.on('leave_room', (data) => {
        console.log('player left : ' + data.player)
        userOnDisconnect(data.player)
    })


    let triggeredByJs = false;

    // Function to be called when button is clicked
    function dropPiece(number) {
        if (triggeredByJs) {
            console.log('Button clicked programmatically at:', number);
        } else {
            console.log('Button clicked by user at:', number);
        }

        // Reset the flag after handling the event
        triggeredByJs = false;
    }

    // Programmatically trigger the click event
    function simulateClick(dynamicNumber) {
        triggeredByJs = true;
        const button = document.querySelector(`div[onclick="dropPiece(${dynamicNumber})"]`);
        if (button) {
            button.click(); // Trigger click programmatically
        }
    }

    socket.on('button_triggered', (data) => {
        btnNumber = data.btn_number
        simulateClick(btnNumber)
        currentPlayer = data.turn
        currentPlayerToggle(currentPlayer)
        console.log(btnNumber + ' was clicked')
    });


    socket.on('reaction_triggered', (data) => {
        btnId = data.btn_id
        console.log(btnId + ' was clicked')
        emoji = document.getElementById(btnId).innerHTML
        addReaction(emoji,btnId,true)
    });
    //
    //
    // GAME LOGIC
    //
    //
    const ROWS = 6;
    const COLS = 7;
    let currentPlayer = 0;
    let gameOver = false;
    let board = Array.from({length: ROWS}, () => Array(COLS).fill(0));
    let rendered = false
    // Create the game board dynamically
    const gameContainer = document.getElementById('gameContainer');

    // Function to render the board based on current state
    function renderBoard(board) {
        gameContainer.innerHTML = '';  // Clear the board

        // Loop through the rows and columns of the board array
        for (let r = 0; r < board.length; r++) {
            for (let c = 0; c < board[r].length; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell');
                gameContainer.appendChild(cell);

                // Check if the cell has a piece (1 for player1, 2 for player2)
                if (board[r][c] === 1) {
                    const piece = document.createElement('div');
                    piece.classList.add('piece', 'player1');  // Add player1 class
                    cell.appendChild(piece);
                } else if (board[r][c] === 2) {
                    const piece = document.createElement('div');
                    piece.classList.add('piece', 'player2');  // Add player2 class
                    cell.appendChild(piece);
                }
            }
        }
    }

    let boardStateTimeout = setTimeout(() => {
        if (!rendered) {
            alert('Connection timed out. Please reload.');
            window.location.reload();
        }
    }, 10000);
    socket.on('board_state', (data) => {
        clearTimeout(boardStateTimeout);
        board = data.board
        currentPlayer = data.turn
        console.log(currentPlayer)
        currentPlayerToggle(currentPlayer)
        renderBoard(board)
        rendered = true
        removeLoader()
    })


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
        console.clear();
        console.log(board)
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


    function checkDraw() {
        for (let r = 0; r < ROWS; r++) {
            for (let c = 0; c < COLS; c++) {
                if (board[r][c] === 0) {
                    return false;  // There is still an empty space, so not a draw
                }
            }
        }
        return true;  // No empty spaces left, so it's a draw
    }


    // Drop a piece into a column
    function dropPiece(col) {
        if (!rendered) return false;
        if (gameOver) {
            alert("Game is over. Please refresh to start a new game.");
            return;
        }

        if (!triggeredByJs) {
            if (currentPlayer != playerCode && !checkWin(currentPlayer)) {
            
                alert(`It's Player ${oppnentName}'s turn.`);
                return;
            }
            socket.emit('button_trigger', {room: roomID, col: col, player_number: playerCode, player_hash: playerHash});
            currentPlayerToggle(opponentCode);
        }
        triggeredByJs = false;

        for (let row = ROWS - 1; row >= 0; row--) {
            if (board[row][col] === 0) {
                board[row][col] = currentPlayer;
                const playerClass = currentPlayer === 1 ? 'player1' : 'player2';
                animateDrop(col, row, playerClass);

                // Check if the current player wins
                if (checkWin(currentPlayer)) {
                    // alert('finnn')
                    document.getElementById('status').innerText = `Player ${currentPlayer} Wins!`;
                    gameOver = true;
                    if (playerCode == currentPlayer) {
                        addReaction('🥇🥇🥇', '1', true);

                    }
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                    return;
                }else if(checkWin(opponentCode)){
                    alert('asdasdasdasds')
                }

                // Check for a draw
                if (checkDraw()) {
                    document.getElementById('status').innerText = "It's a draw!";
                    gameOver = true;
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
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



    //
    //
    // REACTION LOGIC
    //
    //
    let cooldown = false;
    const cooldownDuration = 3000;
    const emojiButton = document.querySelector('.emoji');

    function toggleReactionPopup () {
        if (cooldown) return;

        const popup = document.getElementById('reactionPopup');
        popup.style.display = (popup.style.display === 'block') ? 'none' : 'block';
    }

    function alertUserData(playerNumb) {
        const data = JSON.parse('<?= json_encode($user_data) ?>');
        if (playerNumb == 1){
            playerNumb = 2;
        }else if (playerNumb == 2){
            playerNumb = 1;
        }

        alert(
            'رتبه درصد برد: ' + data[playerNumb]['win_rate_rank'] + "\n" +
            'رتبه تعداد بازی: ' + data[playerNumb]['total_rank'] + "\n" +
            "تعداد جام : " + data[playerNumb]['cups'] + "\n" +
             " شخص "  + data[playerNumb]['activity'] + "روز است که وارد ربات شده است." + "\n" +
            " شخص " + data[playerNumb]['win_rate'] + "% " + " از " + data[playerNumb]['total'] + " بازی را برده است."
        )
    }

    alreadyReported = false;
    opU = opponentCode==1?playerUserName1:playerUserName2

    function reportPlayer(playerCode){
        if (alreadyReported) {
            alert("از قبل گزارش داده اید")
            return false;
        }
        const data = {
            player_number: opponentTelegramCode,
            username:opU
        };
        alreadyReported =true

        fetch(`${location.origin}/XO/pages/report.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded', // Use form data encoding
            },
            body: new URLSearchParams(data)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(result => {
                alert("درخواست شما ارسال گردید.")
                console.log('Success:', result);
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function addFriend() {
        const data = {
            player_number: playerCode,
            hash: playerHash
        };
        fetch(`${location.origin}/XO/pages/friend-req.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded', // Use form data encoding
            },
            body: new URLSearchParams(data)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(result => {
                alert("درخواست شما ارسال گردید.")
                console.log('Success:', result);
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }
    // Function to add a reaction on the screen
    function addReaction(reaction,id,jsClicked=false) {
        if (cooldown) return;
        if(!jsClicked) socket.emit('reaction_trigger', {room: roomID, btn_id: id, player_number: playerCode, player_hash: playerHash});

        const reactionArea = document.getElementById('reactionArea');
        const reactionElement = document.createElement('div');

        reactionElement.innerText = reaction;
        reactionElement.classList.add('reaction');

        const randomX = Math.random() * (reactionArea.clientWidth - 50);
        reactionElement.style.left = `${randomX}px`;
        reactionElement.style.bottom = '0';

        reactionArea.appendChild(reactionElement);

        document.getElementById('reactionPopup').style.display = 'none';

        setTimeout(() => {
            reactionArea.removeChild(reactionElement);
        }, 2000);

        if(!jsClicked) startCooldown();
    }

    // Function to start the cooldown
    function startCooldown() {
        cooldown = true;
        emojiButton.classList.add('disabled');


        setTimeout(() => {
            cooldown = false;
            emojiButton.classList.remove('disabled');
        }, cooldownDuration);
    }
</script>
</body>
</html>

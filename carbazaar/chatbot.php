<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Initialize chat history in session if not already set
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarBazaar AI Assistant</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles (Exactly as provided) */
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-icon {
            font-size: 28px;
            color: var(--primary);
            margin-right: 10px;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }

        .logo-text span {
            color: var(--primary);
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 25px;
        }

        nav ul li a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }

        nav ul li a i {
            margin-right: 8px;
            font-size: 18px;
        }

        nav ul li a:hover {
            color: var(--primary);
        }

        .user-actions {
            display: flex;
            align-items: center;
        }

        .login-margin {
            margin-right: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            outline: none;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Chatbot Container */
        .chatbot-container {
            max-width: 900px;
            margin: 40px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            animation: slideIn 0.5s ease;
            display: flex;
            flex-direction: column;
            min-height: 600px;
            position: relative;
            overflow: hidden;
        }

        .chatbot-header {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            position: relative;
            z-index: 1;
        }

        .chatbot-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?ixlib=rb-4.0.3&auto=format&fit=crop&w=1400&q=80') center/cover no-repeat;
            opacity: 0.2;
            z-index: -1;
        }

        .chatbot-header h2 {
            margin: 0;
            font-size: 30px;
            font-weight: 600;
        }

        .chatbot-header p {
            margin: 8px 0 0;
            font-size: 16px;
            opacity: 0.9;
        }

        .chatbot-body {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            max-height: 450px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background-color: var(--light);
        }

        .message {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            animation: fadeIn 0.3s ease;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .message.user {
            background-color: var(--primary);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }

        .message.assistant {
            background-color: var(--light-gray);
            color: var(--dark);
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }

        .message.assistant strong {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

        .message.assistant ul {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }

        .message.assistant li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
        }

        .message.assistant li::before {
            content: '•';
            color: var(--primary);
            font-size: 16px;
        }

        .thinking {
            display: flex;
            align-items: center;
            gap: 6px;
            align-self: flex-start;
            padding: 10px;
        }

        .thinking .dot {
            width: 10px;
            height: 10px;
            background-color: var(--primary);
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        .thinking .dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .thinking .dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 0.5;
                transform: scale(0.8);
            }
            50% {
                opacity: 1;
                transform: scale(1.2);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chatbot-input {
            display: flex;
            padding: 15px 20px;
            border-top: 1px solid var(--light-gray);
            background-color: white;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
        }

        .chatbot-input input {
            flex: 1;
            padding: 12px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .chatbot-input input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(67, 97, 238, 0.2);
        }

        .chatbot-input button {
            margin-left: 10px;
            padding: 12px 24px;
            font-size: 14px;
            background: var(--success);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .chatbot-input button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
            z-index: -1;
        }

        .chatbot-input button:hover::before {
            left: 100%;
        }

        .chatbot-input button:hover {
            background-color: #0ea5e9;
            transform: translateY(-2px);
        }

        .suggested-questions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-gray);
            background-color: var(--light);
        }

        .suggested-questions .btn {
            font-size: 13px;
            padding: 8px 16px;
            background-color: var(--light-gray);
            color: var(--dark);
            border: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .suggested-questions .btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Footer Styles (Matching about.php) */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 40px 0;
            margin-top: 40px;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .footer-column h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-column li {
            margin-bottom: 10px;
        }

        .footer-column a {
            color: var(--light-gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-column a:hover {
            color: var(--primary);
        }

        .footer-social {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .footer-social a {
            color: white;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .footer-social a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }

        /* Animation for Chatbot Container */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            nav ul {
                flex-direction: column;
                gap: 10px;
            }

            .user-actions {
                flex-direction: column;
                gap: 10px;
            }

            .chatbot-container {
                margin: 20px;
                min-height: 400px;
            }

            .chatbot-body {
                max-height: 300px;
            }

            .chatbot-header h2 {
                font-size: 24px;
            }

            .chatbot-header p {
                font-size: 14px;
            }

            .message {
                max-width: 85%;
            }

            .chatbot-input input {
                font-size: 13px;
            }

            .chatbot-input button {
                font-size: 13px;
                padding: 10px 18px;
            }

            .suggested-questions .btn {
                font-size: 12px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Header (Exactly as provided) -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="logo-text">Car<span>Bazaar</span></div>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="index.php#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                        <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="user-actions">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="user-greeting">
                        Welcome, <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                    <a href="?logout" class="btn btn-outline">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline login-margin">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="chatbot-container">
            <div class="chatbot-header">
                <h2>CarBazaar AI Assistant</h2>
                <p>Welcome to CarBazaar AI Assistant! Ask about buying, selling, or verifying cars.</p>
            </div>
            
            <div class="suggested-questions">
                <button class="btn suggested-question" onclick="sendSuggestedQuestion('How to sell?')">How to sell?</button>
                <button class="btn suggested-question" onclick="sendSuggestedQuestion('Why verify?')">Why verify?</button>
                <button class="btn suggested-question" onclick="sendSuggestedQuestion('Why Choose CarBazaar?')">Why Choose CarBazaar?</button>
                <button class="btn suggested-question" onclick="sendSuggestedQuestion('How to Buy?')">How to Buy?</button>
            </div>

            <div class="chatbot-body" id="chatbotBody">
                <div class="message assistant">
                    Welcome to CarBazaar AI Assistant! I'm here to help you with all your car buying and selling needs. Feel free to ask about how to sell your car, why verification is important, or anything else!
                </div>
                <?php foreach ($_SESSION['chat_history'] as $chat): ?>
                    <div class="message user"><?php echo htmlspecialchars($chat['user']); ?></div>
                    <div class="message assistant"><?php echo nl2br(htmlspecialchars($chat['assistant'])); ?></div>
                <?php endforeach; ?>
            </div>

            <div class="chatbot-input">
                <input type="text" id="userInput" placeholder="Type your question here..." />
                <button class="btn btn-primary" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>
        </div>
    </div>

    <!-- Footer (Matching about.php) -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>CarBazaar</h3>
                    <p>Your trusted platform for buying and selling quality used cars across India. Explore a wide range of vehicles with verified sellers.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#cars">Browse Cars</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                        <li><a href="favorites.php">Favorites</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Help & Support</h3>
                    <ul>
                        <li><a href="about.php#faq">FAQ</a></li>
                        <li><a href="about.php#privacy-policy">Privacy Policy</a></li>
                        <li><a href="about.php#terms-conditions">Terms & Conditions</a></li>
                        <li><a href="about.php#how-to-sell">How to Sell</a></li>
                        <li><a href="about.php#buyer-guide">Buyer Guide</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Changanacherry</li>
                        <li><i class="fas fa-phone-alt"></i> +91 9876543210</li>
                        <li><i class="fas fa-envelope"></i> support@carbazaar.com</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> CarBazaar. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        const API_KEY = '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';
        const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
        const MODEL = 'deepseek/deepseek-r1-0528:free';
        let isProcessing = false;

        // Scroll to bottom of chat
        function scrollToBottom() {
            const chatbotBody = document.getElementById('chatbotBody');
            chatbotBody.scrollTop = chatbotBody.scrollHeight;
        }

        // Add message to chat
        function addMessage(content, role) {
            const chatbotBody = document.getElementById('chatbotBody');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            if (role === 'assistant') {
                // Convert markdown-like formatting to HTML
                content = content
                    .replace(/###\s*(.*?)\n/g, '<strong>$1</strong>\n')
                    .replace(/^\s*-\s*(.*?)$/gm, '<li>$1</li>')
                    .replace(/\n/g, '<br>');
                content = content.replace(/(<li>.*?<\/li>)/g, '<ul>$1</ul>')
                    .replace('</ul><ul>', '');
                messageDiv.innerHTML = content;
            } else {
                messageDiv.innerText = content;
            }
            chatbotBody.appendChild(messageDiv);
            scrollToBottom();
        }

        // Add thinking animation
        function addThinkingAnimation() {
            const chatbotBody = document.getElementById('chatbotBody');
            const thinkingDiv = document.createElement('div');
            thinkingDiv.className = 'thinking';
            thinkingDiv.innerHTML = '<div class="dot"></div><div class="dot"></div><div class="dot"></div>';
            chatbotBody.appendChild(thinkingDiv);
            scrollToBottom();
            return thinkingDiv;
        }

        // Remove thinking animation
        function removeThinkingAnimation(thinkingDiv) {
            if (thinkingDiv) {
                thinkingDiv.remove();
            }
        }

        // Send message to API
        async function sendMessage() {
            if (isProcessing) return;

            const input = document.getElementById('userInput');
            const message = input.value.trim();
            if (!message) return;

            isProcessing = true;
            input.disabled = true;
            addMessage(message, 'user');

            const thinkingDiv = addThinkingAnimation();

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${API_KEY}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        model: MODEL,
                        messages: [
                            { role: 'system', content: 'You are CarBazaar AI Assistant, a helpful assistant for buying and selling cars on the CarBazaar platform.' },
                            ...<?php echo json_encode($_SESSION['chat_history']); ?>.map(chat => [
                                { role: 'user', content: chat.user },
                                { role: 'assistant', content: chat.assistant }
                            ]).flat(),
                            { role: 'user', content: message }
                        ],
                        stream: true
                    })
                });

                const reader = response.body.getReader();
                let accumulatedText = '';
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n');
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.slice(6);
                            if (data === '[DONE]') continue;
                            try {
                                const json = JSON.parse(data);
                                const content = json.choices[0].delta.content;
                                if (content) {
                                    accumulatedText += content;
                                    removeThinkingAnimation(thinkingDiv);
                                    const lastMessage = document.querySelector('.message.assistant:last-child');
                                    if (!lastMessage) {
                                        addMessage(accumulatedText, 'assistant');
                                    } else {
                                        lastMessage.innerHTML = accumulatedText
                                            .replace(/###\s*(.*?)\n/g, '<strong>$1</strong>\n')
                                            .replace(/^\s*-\s*(.*?)$/gm, '<li>$1</li>')
                                            .replace(/\n/g, '<br>')
                                            .replace(/(<li>.*?<\/li>)/g, '<ul>$1</ul>')
                                            .replace('</ul><ul>', '');
                                    }
                                    scrollToBottom();
                                }
                            } catch (e) {
                                console.error('Error parsing chunk:', e);
                            }
                        }
                    }
                }

                // Save to session
                const formData = new FormData();
                formData.append('user', message);
                formData.append('assistant', accumulatedText);
                await fetch('save_chat.php', {
                    method: 'POST',
                    body: formData
                });

                input.value = '';
            } catch (error) {
                console.error('Error:', error);
                addMessage('Sorry, something went wrong. Please try again.', 'assistant');
            } finally {
                removeThinkingAnimation(thinkingDiv);
                input.disabled = false;
                isProcessing = false;
            }
        }

        // Send suggested question
        function sendSuggestedQuestion(question) {
            document.getElementById('userInput').value = question;
            sendMessage();
        }

        // Handle Enter key
        document.getElementById('userInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Scroll to bottom on load
        window.onload = scrollToBottom;
    </script>
</body>
</html>
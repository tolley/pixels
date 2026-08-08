<?php namespace pixels\Controller;
/**
 * A "main" controller to handle routes for things like the
 * homepage, the image, and anything else I need
 */
use Slim\Views\PhpRenderer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use pixels\functions;

class mainController {
    private $container;

    public function __construct( ContainerInterface $container ) {
        $this->container = $container;
    }
    
    public function home( Request $req, Response $resp, array $args ) {
        $renderer = new PhpRenderer( './templates' );

        $hasGoogle = array_key_exists( 'GOOGLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['GOOGLE_CLIENT_ID'] ) > 0;
        $hasGoogle = false;
        $hasApple = array_key_exists( 'APPLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['APPLE_CLIENT_ID'] ) > 0;
        $hasSocial = $hasGoogle || $hasApple;

        $viewData = [
            'cssBodyClass' => 'auth home',
            'bodyTemplate' => 'index.php',
            'bodyData' => [ 
                'hasSocial' => $hasSocial,
                'hasGoogle' => $hasGoogle,
                'hasApple' => $hasApple
            ]
        ];

        return $renderer->render( $resp, 'main.php', $viewData );
    }

    public function signup( Request $req, Response $resp, array $args ) {
        $renderer = new PhpRenderer( './templates' );

        $hasGoogle = array_key_exists( 'GOOGLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['GOOGLE_CLIENT_ID'] ) > 0;
        $hasGoogle = false;
        $hasApple = array_key_exists( 'APPLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['APPLE_CLIENT_ID'] ) > 0;
        $hasSocial = $hasGoogle || $hasApple;
    
        $viewData = [
            'cssBodyClass' => 'auth',
            'bodyTemplate' => 'signup.php',
            'bodyData' => [ 
                'hasSocial' => $hasSocial,
                'hasGoogle' => $hasGoogle,
                'hasApple' => $hasApple
            ]
        ];

        return $renderer->render( $resp, 'main.php', $viewData );
    }

    public function doSignup( Request $req, Response $resp, array $args ) {
        $data = json_decode((string) $req->getBody(), true);

        $username = trim( $data['username'] ?? '' );
        $email    = trim( $data['email'] ?? '');
        $password = $data['password'] ?? '';

        if( !$username || !$email || !$password ) {
            http_response_code( 400 );
            $resp->getBody()->write( json_encode(['error' => 'Username, email and password are required'] ) );
            return $resp;
        }

        if( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            http_response_code( 400 );
            $resp->getBody()->write( json_encode( ['error' => 'Invalid email address'] ) );
            return $resp;
        }

        if( strlen( $username ) < 3 || strlen( $username ) > 64 ) {
            http_response_code( 400 );
            $resp->getBody()->write( json_encode( ['error' => 'Username must be 3–64 characters'] ) );
            return $resp;
        }
        if( strlen($password) < 8 ) {
            http_response_code( 400 );
            $resp->getBody()->write( json_encode( ['error' => 'Password must be at least 8 characters'] ) );
            return $resp;
        }

        $pdo = $this->container->get( 'pdo' );

        $stmt = $pdo->prepare( 'SELECT id, email FROM users WHERE email = ? OR username = ?' );
        $stmt->execute( [$email, $username] );
        if( $existing = $stmt->fetch() ) {
            $respBody = json_encode(
                            ['error' => $existing['email'] === $email
                                    ? 'Email already registered'
                                    : 'Username already taken'] );
            $resp->withStatus( 409 );
            $resp->getBody()->write( $respBody );
            return $resp;
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
        $stmt->execute( [$username, $email, password_hash( $password, PASSWORD_BCRYPT ) ] );
        $userId = (int)$pdo->lastInsertId();

        $token = \pixels\functions\createVerifyToken( $pdo, $userId );
        \pixels\functions\sendVerificationEmail( $email, $username, $token );
        $resp->getBody()->write( json_encode( ['ok' => true, 'message' => "Check $email for a verification link"] ) );

        return $resp;
    }

    public function login( Request $req, Response $resp, array $args ) {
        $renderer = new PhpRenderer( './templates' );

        $hasGoogle = array_key_exists( 'GOOGLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['GOOGLE_CLIENT_ID'] ) > 0;
        $hasGoogle = false;
        $hasApple = array_key_exists( 'APPLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['APPLE_CLIENT_ID'] ) > 0;
        $hasSocial = $hasGoogle || $hasApple;
    
        $viewData = [
            'cssBodyClass' => 'auth',
            'bodyTemplate' => 'login.php',
            'bodyData' => [ 
                'hasSocial' => $hasSocial,
                'hasGoogle' => $hasGoogle,
                'hasApple' => $hasApple
            ]
        ];

        return $renderer->render( $resp, 'main.php', $viewData );
    }

    public function loginLanding( Request $req, Response $resp, array $args ) {
        $renderer = new PhpRenderer( './templates' );
        $viewData = [];
        return $renderer->render( $resp, 'loginlanding.php', $viewData );
    }


    /**
     * API to get the currently logged in user's data for the front end
     * 
     * @return array The user's data
     */
    public function getMe( Request $req, Response $resp, array $args ): Response {

        $pdo = $this->container->get( 'pdo' );

        $token = $_COOKIE['jwt'];
        $userInfo = \pixels\functions\jwtDecode( $token );
        $username = $userInfo['username'];
        
        $userData = \pixels\functions\getUserInfo( $pdo, $username );

        $resp->getBody()->write( json_encode( $userData ) );
        return $resp;
    }

    /**
     * The page that shows the current rendered image
     */
    public function image( Request $req, Response $resp, array $args ): Response {
        // $user = jwtFromRequest();

        $x1 = ( $req->getQueryParam( 'x1' ) )? (int)$req->getQueryParam( 'x1' ): 0;
        $x2 = ( $req->getQueryParam( 'x2' ) )? (int)$req->getQueryParam( 'x2' ): 300;
        $y1 = ( $req->getQueryParam( 'y1' ) )? (int)$req->getQueryParam( 'y1' ): 0;
        $y2 = ( $req->getQueryParam( 'y2' ) )? (int)$req->getQueryParam( 'y2' ): 300;
        $scale = ( $req->getQueryParam( 'scale',  ) )? (int)$req->getQueryParam( 'scale' ): 4; 

        $imgUrl = "/image-api?x1=$x1&x2=$x2&y1=$y1&y2=$y2&scale=$scale";

        $cellsW = $x2 - $x1 + 1;
        $cellsH = $y2 - $y1 + 1;
        $imgW   = $cellsW * $scale;
        $imgH   = $cellsH * $scale;
        
        // Calculate the needed data and render the image.php template with the url
        return $resp;
    }

    /**
     * Returns true if the user has emails allowe, false otherwise
     * 
     * @returns array   Containing bool for email permission, true allowed, false otherwise
     */
    public function emailsAllowed( Request $req, Response $resp, array $args ) {
        $userInfo = \pixels\functions\getUserFromJWT();
        // $resp->getBody()->write( (string)$userInfo['email_allowed'] );

        $pdo = $this->container->get( 'pdo' );

        $userInfo = \pixels\functions\getUserFromJWT();
        $userData = \pixels\functions\getUserInfo( $pdo, $userInfo['username'] );
        
        $resp->getBody()->write( (string)$userData['email_allowed'] );

        return $resp;
    }

    /**
     * Toggles the user's email allowed setting
     */
    public function toggleEmailsAllowed( Request $req, Response $resp, array $args ): Response {
        $pdo = $this->container->get( 'pdo' );
        $userInfo = \pixels\functions\getUserFromJWT();
        $userData = \pixels\functions\getUserInfo( $pdo, $userInfo['username'] );

        // Had to do this to make PHP use a string and still get
        // the bool part correct
        if( $userData['email_allowed'] === 1 )
            $updateEA = 0;
        else
            $updateEA = 1;

        $arr = [
            (string)$updateEA,
            $userInfo['username']
        ];

        $stmt = $pdo->prepare( 'UPDATE users set email_allowed = ? WHERE username = ?',
                $arr );

        $stmt->execute( $arr );

        $resp->getBody()->write( (string)$updateEA );
        return $resp;
    }
}
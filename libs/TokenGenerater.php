<?php
require __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\SignatureInvalidException;
class Token {
    public static function getToken($user) {
        $tokenId = base64_encode(random_bytes(32));
        $issuedAt = time();
        $expire = $issuedAt + 230400;  // Expire in 24 hours
        $issuer = 'Tic Tac Toe';
        $secretKey = SecretKey;
        $token = [
            'iat'  => $issuedAt,
            'jti'  => $tokenId,
            'iss'  => $issuer,
            'exp'  => $expire, // Add this line to include the expiration time in the token
            'userId'   => $user['id'],
            'username' => $user['username'],
        ];
    
        $jwt = JWT::encode($token, $secretKey, 'HS256');
        return $jwt;
    }

    public static function decodeToken($token, $secretKey){
        try {
            $decoded = JWT::decode($token, new Key(SecretKey, 'HS256'));
            // print_r($decoded);
            $now = new DateTimeImmutable();
            if ($decoded->exp < $now->getTimestamp()) {
                throw new Exception('Token has expired.');
            }
            return $decoded;
        }  catch (ExpiredException $e) {
           return ['error' => 'Token has expired',"status" =>401];
        } catch (BeforeValidException | SignatureInvalidException $e) {
            return ['error' => 'Token verification failed',"status" =>401];
        } catch (\Exception $e) {
           return['error' => 'Internal Server Error',"status" =>401];
        } 
    }
}
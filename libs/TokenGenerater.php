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
        $expire = $issuedAt + 3600;  // Expire in 1 hour
        $issuer = 'Tic Tac Toe';
        $secretKey=SecretKey;
        $token = [
            'iat'  => $issuedAt,
            'jti'  => $tokenId,
            'iss'  => $issuer,
           
            'userId'   => $user['id'],
            'username' => $user['username'],
          
        ];
    
        $jwt = JWT::encode($token, $secretKey, 'HS256');
        return $jwt;
    }
    public static function decodeToken($token, $secretKey){
        try {
            $decoded = JWT::decode($token, new Key(SecretKey, 'HS256'));
            return $decoded;
        }  catch (ExpiredException $e) {
           return ['error' => 'Token has expired'];
        } catch (BeforeValidException | SignatureInvalidException $e) {
            return ['error' => 'Token verification failed'];
        } catch (\Exception $e) {
           return['error' => 'Internal Server Error'];
        } 
    }
}
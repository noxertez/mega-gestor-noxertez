<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}
?>
<?php
class SRP6
{
    private static $g = 7;
    private static $N_hex = '894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7';

    public static function GenerateSalt()
    {
        return random_bytes(32); // Generate a 32-byte random salt
    }

    public static function calculateVerifier($username, $password, $salt)
    {
        $username = strtoupper($username);
        $password = strtoupper($password);

        // H1 = SHA1(USERNAME:PASSWORD)
        $h1 = sha1($username . ':' . $password, true);

        // H2 = SHA1(salt | H1)
        $h2 = sha1($salt . $h1, true);

        // Convert H2 to GMP integer (little-endian)
        $x = gmp_import($h2, 1, GMP_LSW_FIRST);

        // N and g
        $N = gmp_init(self::$N_hex, 16);
        $g = gmp_init(self::$g);

        // v = g^x mod N
        $v = gmp_powm($g, $x, $N);

        // Convert to little-endian binary and pad to 32 bytes
        $verifier = gmp_export($v, 1, GMP_LSW_FIRST);
        return str_pad($verifier, 32, "\0", STR_PAD_RIGHT);
    }

    /**
     * Helper to get both salt and verifier for a new registration.
     * @param string $username
     * @param string $password
     * @return array [binary_salt, binary_verifier]
     */
    public static function getRegistrationData($username, $password)
    {
        $salt = self::GenerateSalt();
        $verifier = self::calculateVerifier($username, $password, $salt);
        return [$salt, $verifier];
    }

    public static function verifyPassword($username, $password, $salt, $storedVerifier)
    {
        $computedVerifier = self::calculateVerifier($username, $password, $salt);
        return hash_equals($computedVerifier, $storedVerifier);
    }
}
?>
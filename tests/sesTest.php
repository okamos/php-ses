<?php

/**
 * SimpleEmailService Class test.
 *
 * PHP Version 5 | 7
 *
 * @category Class
 * @package  AmazonSimpleEmailService
 * @author   Okamos <okamoto@okamos.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/okamos/php-ses
 */
use PHPUnit\Framework\TestCase;

// TODO: test identities count.
class sesTest extends TestCase
{
    protected static $uniqid;

    public static function setUpBeforeClass()
    {
        self::$uniqid = uniqid(rand(), true);
    }

    /**
     * Setup AWS Account from ENVIRONMENT variables.
     * * AWS_ACCESS_KEY_ID
     * * AWS_SECRET_ACCESS_KEY
     * * REGION_NAME
     */
    public function setUp()
    {
        $aws_key = getenv('AWS_ACCESS_KEY_ID');
        $aws_secret = getenv('AWS_SECRET_ACCESS_KEY');
        $region = getenv('REGION');

        $uniqid = self::$uniqid;
        $this->_email = "okamos-{$uniqid}@okamos.com";
        $this->_domain = 'okamos.com';
        $this->_client = new SimpleEmailService(
            $aws_key,
            $aws_secret,
            $region
        );
    }

    public function testVerifyEmailIdentity()
    {
        $requestId = $this->_client->verifyEmailIdentity($this->_email);
        $this->assertNotEmpty($requestId);
    }

    public function testListIdentities()
    {
        $identities =  $this->_client->listIdentities();
        $this->assertContains($this->_email, $identities);
    }

    public function testDeleteIdentity()
    {
        $requestId = $this->_client->deleteIdentity($this->_email);
        $this->assertNotEmpty($requestId);
    }

    public function testGetIdentityVerificationAttributes()
    {
        $entries = $this->_client->getIdentityVerificationAttributes(
            [$this->_domain]
        );
        $this->assertEquals(
            'Success', $entries[0]['Status']
        );
    }
}
?>

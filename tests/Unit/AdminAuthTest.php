<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/site/includes/auth.php';

/**
 * Unit tests for admin_password_verify / zxpress_user_is_admin_level (no DB).
 */
final class AdminAuthTest extends TestCase
{
	public function testPasswordVerifyBcrypt(): void
	{
		$plain = 'secret';
		$hash = password_hash($plain, PASSWORD_DEFAULT);
		self::assertTrue(admin_password_verify($plain, $hash));
		self::assertFalse(admin_password_verify('wrong', $hash));
	}

	public function testPasswordVerifyRejectsLegacyMd5Hex(): void
	{
		$plain = 'legacy';
		$md5 = md5($plain);
		self::assertFalse(admin_password_verify($plain, $md5));
	}

	public function testPasswordVerifyTrimsStoredHash(): void
	{
		$plain = 'x';
		$hash = password_hash($plain, PASSWORD_DEFAULT);
		self::assertTrue(admin_password_verify($plain, ' ' . $hash . ' '));
	}

	public function testZxpressUserIsAdminLevel(): void
	{
		self::assertTrue(zxpress_user_is_admin_level(null));
		self::assertTrue(zxpress_user_is_admin_level(1));
		self::assertTrue(zxpress_user_is_admin_level('1'));
		self::assertFalse(zxpress_user_is_admin_level(0));
		self::assertFalse(zxpress_user_is_admin_level(2));
	}
}

<?php

namespace Threespot\Wp\MuPlugins;

/**
 * SMTP configuration (Mailhog on Pantheon lando local env by default).
 *
 * Filters:
 *   threespot/smtp/should_configure — bool, whether to override PHPMailer (default: PANTHEON_ENVIRONMENT === 'lando')
 *   threespot/smtp/config           — array of phpmailer settings (host/port/auth/username/password)
 */
class SmtpConfig
{
    /**
     * Wire the PHPMailer hook only when this environment opts in.
     * Skipping `add_action` here means production sites pay zero cost.
     */
    public static function register(): void
    {
        if (!self::shouldConfigure()) {
            return;
        }

        add_action('phpmailer_init', [self::class, 'configurePhpMailer']);
    }

    /**
     * Whether the package should override PHPMailer at all. Defaults to true
     * only on Lando-local environments (Mailhog is the local catch-all SMTP),
     * but sites can force it on/off via the `threespot/smtp/should_configure` filter.
     */
    public static function shouldConfigure(): bool
    {
        $is_lando = isset($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] === 'lando';
        return (bool) apply_filters('threespot/smtp/should_configure', $is_lando);
    }

    /**
     * Point PHPMailer at the SMTP server returned by the `threespot/smtp/config` filter.
     *
     * WP fires `phpmailer_init` with the PHPMailer instance passed by reference,
     * so mutations on `$phpmailer` apply to the in-flight send.
     *
     * @param object $phpmailer The PHPMailer instance (passed by reference by WordPress).
     */
    public static function configurePhpMailer($phpmailer): void
    {
        $config = apply_filters('threespot/smtp/config', [
            'host' => 'mailhog',
            'port' => 1025,
            'auth' => false,
            'username' => null,
            'password' => null,
        ]);

        $phpmailer->isSMTP();
        $phpmailer->Host = $config['host'];
        $phpmailer->Port = $config['port'];
        $phpmailer->SMTPAuth = (bool) $config['auth'];

        if ($config['auth']) {
            $phpmailer->Username = $config['username'];
            $phpmailer->Password = $config['password'];
        }
    }
}

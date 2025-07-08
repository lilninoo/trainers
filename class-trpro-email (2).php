<?php
/**
 * Classe pour la gestion avancée des emails
 * 
 * Fichier: /wp-content/plugins/trainer-registration-plugin/includes/class-trpro-email.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TrproEmailManager {
    
    private static $instance = null;
    private $email_templates = array();
    private $email_queue = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_email_templates();
        add_action('wp_loaded', array($this, 'process_email_queue'));
        add_action('trpro_send_scheduled_email', array($this, 'send_scheduled_email'), 10, 3);
    }
    
    /**
     * Initialiser les templates d'email
     */
    private function init_email_templates() {
        $this->email_templates = array(
            'trainer_registration_confirmation' => array(
                'subject' => 'Confirmation de votre inscription - Plateforme Formateurs IT',
                'template' => 'registration-confirmation',
                'description' => 'Email de confirmation envoyé au formateur après inscription'
            ),
            'trainer_approved' => array(
                'subject' => 'Félicitations ! Votre candidature a été approuvée',
                'template' => 'trainer-approved',
                'description' => 'Email envoyé quand le formateur est approuvé'
            ),
            'trainer_rejected' => array(
                'subject' => 'Mise à jour de votre candidature',
                'template' => 'trainer-rejected',
                'description' => 'Email envoyé quand le formateur est rejeté'
            ),
            'admin_new_trainer' => array(
                'subject' => 'Nouvelle inscription formateur - Action requise',
                'template' => 'admin-new-trainer',
                'description' => 'Email envoyé à l\'admin pour nouvelle inscription'
            ),
            'trainer_contact_request' => array(
                'subject' => 'Nouvelle demande de contact',
                'template' => 'contact-request',
                'description' => 'Email pour demandes de contact'
            ),
            'weekly_summary' => array(
                'subject' => 'Résumé hebdomadaire - Plateforme Formateurs',
                'template' => 'weekly-summary',
                'description' => 'Résumé hebdomadaire des activités'
            ),
            'pending_reminder' => array(
                'subject' => 'Formateurs en attente de validation',
                'template' => 'pending-reminder',
                'description' => 'Rappel pour formateurs en attente'
            )
        );
    }
    
    /**
     * Envoyer un email avec template
     */
    public function send_template_email($template_key, $to, $data = array(), $schedule = false) {
        if (!isset($this->email_templates[$template_key])) {
            error_log("TrproEmailManager: Template $template_key not found");
            return false;
        }
        
        $template = $this->email_templates[$template_key];
        
        // Générer le contenu de l'email
        $email_content = $this->generate_email_content($template['template'], $data);
        $subject = $this->parse_template_vars($template['subject'], $data);
        
        if ($schedule) {
            return $this->schedule_email($to, $subject, $email_content, $data);
        } else {
            return $this->send_email($to, $subject, $email_content);
        }
    }
    
    /**
     * Envoyer un email immédiatement
     */
    public function send_email($to, $subject, $message, $headers = array()) {
        $default_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>'
        );
        
        $headers = array_merge($default_headers, $headers);
        
        // Wrapper HTML
        $html_message = $this->wrap_email_content($message, $subject);
        
        // Log de l'envoi
        $this->log_email_sent($to, $subject);
        
        return wp_mail($to, $subject, $html_message, $headers);
    }
    
    /**
     * Programmer un email
     */
    public function schedule_email($to, $subject, $message, $data = array(), $delay = 0) {
        $scheduled_time = time() + $delay;
        
        return wp_schedule_single_event(
            $scheduled_time,
            'trpro_send_scheduled_email',
            array($to, $subject, $message)
        );
    }
    
    /**
     * Envoyer un email programmé
     */
    public function send_scheduled_email($to, $subject, $message) {
        return $this->send_email($to, $subject, $message);
    }
    
    /**
     * Générer le contenu d'un email depuis un template
     */
    private function generate_email_content($template_name, $data = array()) {
        ob_start();
        
        switch ($template_name) {
            case 'registration-confirmation':
                echo $this->get_registration_confirmation_template($data);
                break;
                
            case 'trainer-approved':
                echo $this->get_trainer_approved_template($data);
                break;
                
            case 'trainer-rejected':
                echo $this->get_trainer_rejected_template($data);
                break;
                
            case 'admin-new-trainer':
                echo $this->get_admin_new_trainer_template($data);
                break;
                
            case 'contact-request':
                echo $this->get_contact_request_template($data);
                break;
                
            case 'weekly-summary':
                echo $this->get_weekly_summary_template($data);
                break;
                
            case 'pending-reminder':
                echo $this->get_pending_reminder_template($data);
                break;
                
            default:
                echo '<p>Template non trouvé.</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Template de confirmation d'inscription
     */
    private function get_registration_confirmation_template($data) {
        $trainer_name = isset($data['first_name']) ? $data['first_name'] : 'Formateur';
        $company_name = get_option('trainer_company_name', get_bloginfo('name'));
        
        return "
        <div style='background: #f8fafc; padding: 40px 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 40px; text-align: center; color: white;'>
                    <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>Bienvenue {$trainer_name} !</h1>
                    <p style='margin: 16px 0 0 0; font-size: 18px; opacity: 0.9;'>Votre inscription a été reçue avec succès</p>
                </div>
                
                <div style='padding: 40px;'>
                    <div style='text-align: center; margin-bottom: 32px;'>
                        <div style='width: 80px; height: 80px; background: #e0e7ff; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;'>
                            <span style='font-size: 32px;'>✅</span>
                        </div>
                        <h2 style='color: #1f2937; margin: 0 0 8px 0;'>Inscription Confirmée</h2>
                        <p style='color: #6b7280; margin: 0;'>Nous examinons actuellement votre candidature</p>
                    </div>
                    
                    <div style='background: #f9fafb; border-radius: 8px; padding: 24px; margin-bottom: 32px;'>
                        <h3 style='color: #374151; margin: 0 0 16px 0; font-size: 18px;'>Prochaines étapes :</h3>
                        <div style='space-y: 12px;'>
                            <div style='display: flex; align-items: center; margin-bottom: 12px;'>
                                <span style='width: 24px; height: 24px; background: #10b981; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 12px;'>1</span>
                                <span style='color: #4b5563;'>Vérification de votre profil et documents</span>
                            </div>
                            <div style='display: flex; align-items: center; margin-bottom: 12px;'>
                                <span style='width: 24px; height: 24px; background: #f59e0b; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 12px;'>2</span>
                                <span style='color: #4b5563;'>Validation par notre équipe sous 48h</span>
                            </div>
                            <div style='display: flex; align-items: center;'>
                                <span style='width: 24px; height: 24px; background: #6366f1; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 12px;'>3</span>
                                <span style='color: #4b5563;'>Activation de votre profil formateur</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin-bottom: 32px;'>
                        <p style='color: #6b7280; margin: 0 0 20px 0;'>En attendant, n'hésitez pas à nous contacter pour toute question</p>
                        <a href='mailto:" . get_option('trainer_contact_email', get_option('admin_email')) . "' style='background: #6366f1; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;'>Nous contacter</a>
                    </div>
                </div>
                
                <div style='background: #f9fafb; padding: 24px; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                        Cet email a été envoyé par {$company_name}<br>
                        Si vous n'êtes pas à l'origine de cette inscription, veuillez ignorer cet email.
                    </p>
                </div>
            </div>
        </div>";
    }
    
    /**
     * Template de formateur approuvé
     */
    private function get_trainer_approved_template($data) {
        $trainer_name = isset($data['first_name']) ? $data['first_name'] : 'Formateur';
        $admin_url = admin_url('admin.php?page=trainer-registration');
        
        return "
        <div style='background: #f0fdf4; padding: 40px 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background: linear-gradient(135deg, #10b981, #059669); padding: 40px; text-align: center; color: white;'>
                    <div style='font-size: 48px; margin-bottom: 16px;'>🎉</div>
                    <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>Félicitations {$trainer_name} !</h1>
                    <p style='margin: 16px 0 0 0; font-size: 18px; opacity: 0.9;'>Votre candidature a été approuvée</p>
                </div>
                
                <div style='padding: 40px;'>
                    <div style='text-align: center; margin-bottom: 32px;'>
                        <h2 style='color: #1f2937; margin: 0 0 16px 0;'>Bienvenue dans notre réseau !</h2>
                        <p style='color: #6b7280; line-height: 1.6;'>
                            Votre profil de formateur expert est maintenant actif sur notre plateforme. 
                            Les recruteurs peuvent désormais consulter vos compétences et vous contacter 
                            pour des opportunités de formation.
                        </p>
                    </div>
                    
                    <div style='background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 24px; margin-bottom: 32px;'>
                        <h3 style='color: #065f46; margin: 0 0 16px 0; font-size: 18px;'>Avantages de votre adhésion :</h3>
                        <ul style='color: #047857; margin: 0; padding-left: 20px;'>
                            <li style='margin-bottom: 8px;'>Visibilité auprès de recruteurs qualifiés</li>
                            <li style='margin-bottom: 8px;'>Accès prioritaire aux missions de formation</li>
                            <li style='margin-bottom: 8px;'>Support dédié pour vos candidatures</li>
                            <li style='margin-bottom: 8px;'>Réseau d'experts pour échanger</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center;'>
                        <p style='color: #6b7280; margin: 0 0 20px 0;'>Votre profil est maintenant visible par les recruteurs</p>
                        <div style='display: inline-block; margin: 0 8px;'>
                            <a href='mailto:" . get_option('trainer_contact_email', get_option('admin_email')) . "' style='background: #10b981; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-right: 12px;'>Nous contacter</a>
                        </div>
                    </div>
                </div>
                
                <div style='background: #f9fafb; padding: 24px; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                        Merci de faire partie de notre réseau d'excellence !
                    </p>
                </div>
            </div>
        </div>";
    }
    
    /**
     * Template de formateur rejeté
     */
    private function get_trainer_rejected_template($data) {
        $trainer_name = isset($data['first_name']) ? $data['first_name'] : 'Formateur';
        $reason = isset($data['rejection_reason']) ? $data['rejection_reason'] : '';
        
        return "
        <div style='background: #fef2f2; padding: 40px 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <div style='background: linear-gradient(135deg, #f59e0b, #d97706); padding: 40px; text-align: center; color: white;'>
                    <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>Mise à jour de votre candidature</h1>
                    <p style='margin: 16px 0 0 0; font-size: 18px; opacity: 0.9;'>Bonjour {$trainer_name}</p>
                </div>
                
                <div style='padding: 40px;'>
                    <p style='color: #6b7280; line-height: 1.6; margin-bottom: 24px;'>
                        Nous vous remercions pour l'intérêt que vous portez à notre plateforme de formateurs.
                        Après examen de votre candidature, nous ne pouvons malheureusement pas 
                        l'accepter en l'état actuel.
                    </p>
                    
                    " . (!empty($reason) ? "
                    <div style='background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin-bottom: 24px;'>
                        <h3 style='color: #92400e; margin: 0 0 12px 0;'>Motif :</h3>
                        <p style='color: #a16207; margin: 0;'>{$reason}</p>
                    </div>
                    " : "") . "
                    
                    <div style='background: #f0f9ff; border: 1px solid #7dd3fc; border-radius: 8px; padding: 20px; margin-bottom: 24px;'>
                        <h3 style='color: #0c4a6e; margin: 0 0 12px 0;'>Vous pouvez :</h3>
                        <ul style='color: #0369a1; margin: 0; padding-left: 20px;'>
                            <li style='margin-bottom: 8px;'>Compléter votre profil avec plus d'informations</li>
                            <li style='margin-bottom: 8px;'>Ajouter des certifications récentes</li>
                            <li style='margin-bottom: 8px;'>Mettre à jour votre CV avec vos dernières expériences</li>
                            <li>Nous recontacter dans quelques mois</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center;'>
                        <p style='color: #6b7280; margin: 0 0 20px 0;'>
                            N'hésitez pas à nous contacter pour plus d'informations
                        </p>
                        <a href='mailto:" . get_option('trainer_contact_email', get_option('admin_email')) . "' style='background: #f59e0b; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;'>Nous contacter</a>
                    </div>
                </div>
                
                <div style='background: #f9fafb; padding: 24px; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; color: #6b7280; font-size: 14px;'>
                        Merci pour votre compréhension.
                    </p>
                </div>
            </div>
        </div>";
    }
    
    /**
     * Template pour notification admin
     */
    private function get_admin_new_trainer_template($data) {
        $trainer_name = isset($data['first_name']) && isset($data['last_name']) 
            ? $data['first_name'] . ' ' . $data['last_name'] 
            : 'Nouveau formateur';
        $trainer_id = isset($data['trainer_id']) ? $data['trainer_id'] : '';
        $trainer_email = isset($data['email']) ? $data['email'] : '';
        $trainer_phone = isset($data['phone']) ? $data['phone'] : '';
        $trainer_specialties = isset($data['specialties']) ? $data['specialties'] : '';
        $admin_url = admin_url('admin.php?page=trainer-registration&action=view&trainer_id=' . $trainer_id);
        
        return "
        <div style='background: #f8fafc; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <div style='background: #6366f1; color: white; padding: 24px; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Nouvelle inscription formateur</h1>
                    <p style='margin: 8px 0 0 0; opacity: 0.9;'>Action requise</p>
                </div>
                
                <div style='padding: 24px;'>
                    <div style='background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin-bottom: 24px;'>
                        <p style='margin: 0; color: #92400e; font-weight: 600;'>
                            ⚠️ Un nouveau formateur attend votre validation
                        </p>
                    </div>
                    
                    <h2 style='color: #1f2937; margin: 0 0 16px 0;'>{$trainer_name}</h2>
                    
                    <div style='background: #f9fafb; padding: 16px; border-radius: 6px; margin-bottom: 20px;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; width: 120px;'>ID :</td>
                                <td style='padding: 8px 0; color: #1f2937; font-weight: 600;'>#{$trainer_id}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280;'>Email :</td>
                                <td style='padding: 8px 0; color: #1f2937;'>{$trainer_email}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280;'>Téléphone :</td>
                                <td style='padding: 8px 0; color: #1f2937;'>{$trainer_phone}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; vertical-align: top;'>Spécialités :</td>
                                <td style='padding: 8px 0; color: #1f2937;'>{$trainer_specialties}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='text-align: center; margin: 32px 0;'>
                        <a href='{$admin_url}' style='background: #10b981; color: white; padding: 14px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-right: 12px;'>Examiner le profil</a>
                        <a href='" . admin_url('admin.php?page=trainer-registration') . "' style='background: #6b7280; color: white; padding: 14px 28px; border-radius: 6px; text-decoration: none; font-weight: 600;'>Voir tous les formateurs</a>
                    </div>
                </div>
            </div>
        </div>";
    }
    
    /**
     * Template pour demande de contact
     */
    private function get_contact_request_template($data) {
        return "<!-- Template de demande de contact -->
        <p><strong>Nouvelle demande de contact reçue</strong></p>
        <p>Détails de la demande...</p>";
    }
    
    /**
     * Template pour résumé hebdomadaire
     */
    private function get_weekly_summary_template($data) {
        return "<!-- Template de résumé hebdomadaire -->
        <p><strong>Résumé hebdomadaire</strong></p>
        <p>Statistiques de la semaine...</p>";
    }
    
    /**
     * Template pour rappel formateurs en attente
     */
    private function get_pending_reminder_template($data) {
        $pending_count = isset($data['pending_count']) ? $data['pending_count'] : 0;
        
        return "
        <div style='background: #fef3c7; padding: 20px; border-radius: 8px; border: 1px solid #fcd34d;'>
            <h2 style='color: #92400e; margin: 0 0 16px 0;'>Formateurs en attente</h2>
            <p style='color: #a16207; margin: 0 0 16px 0;'>
                Vous avez <strong>{$pending_count} formateur(s)</strong> en attente de validation depuis plus de 7 jours.
            </p>
            <a href='" . admin_url('admin.php?page=trainer-registration&status_filter=pending') . "' 
               style='background: #f59e0b; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;'>
                Traiter maintenant
            </a>
        </div>";
    }
    
    /**
     * Wrapper HTML pour les emails
     */
    private function wrap_email_content($content, $subject) {
        $company_name = get_option('trainer_company_name', get_bloginfo('name'));
        $company_email = get_option('trainer_contact_email', get_option('admin_email'));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$subject}</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; line-height: 1.6; color: #333;'>
            {$content}
        </body>
        </html>";
    }
    
    /**
     * Remplacer les variables dans les templates
     */
    private function parse_template_vars($text, $data) {
        foreach ($data as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
    
    /**
     * Obtenir l'email expéditeur
     */
    private function get_from_email() {
        return get_option('trainer_contact_email', get_option('admin_email'));
    }
    
    /**
     * Obtenir le nom expéditeur
     */
    private function get_from_name() {
        return get_option('trainer_company_name', get_bloginfo('name'));
    }
    
    /**
     * Logger les emails envoyés
     */
    private function log_email_sent($to, $subject) {
        if (get_option('trainer_debug_mode', 0)) {
            error_log("TrproEmailManager: Email sent to {$to} - Subject: {$subject}");
        }
    }
    
    /**
     * Traiter la queue d'emails
     */
    public function process_email_queue() {
        // Traitement de la queue d'emails si nécessaire
        // Pour l'instant, les emails sont envoyés directement
    }
    
    /**
     * Envoyer un résumé hebdomadaire
     */
    public function send_weekly_summary() {
        if (!get_option('trainer_notify_weekly_summary', 0)) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'trainer_registrations';
        
        // Statistiques de la semaine
        $stats = array(
            'new_registrations' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
            'approved_this_week' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'approved' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
            'pending_total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'")
        );
        
        $admin_email = get_option('trainer_notification_email', get_option('admin_email'));
        
        return $this->send_template_email('weekly_summary', $admin_email, $stats);
    }
    
    /**
     * Envoyer des rappels pour formateurs en attente
     */
    public function send_pending_reminders() {
        if (!get_option('trainer_notify_pending_review', 1)) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'trainer_registrations';
        
        $pending_count = $wpdb->get_var("
            SELECT COUNT(*) FROM $table_name 
            WHERE status = 'pending' 
            AND created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        if ($pending_count > 0) {
            $admin_email = get_option('trainer_notification_email', get_option('admin_email'));
            return $this->send_template_email('pending_reminder', $admin_email, array('pending_count' => $pending_count));
        }
        
        return false;
    }
    
    /**
     * Test de l'envoi d'email
     */
    public function test_email_sending($to = null) {
        $test_email = $to ?: get_option('admin_email');
        
        $subject = 'Test Email - Trainer Registration Pro';
        $message = '
        <div style="padding: 20px; background: #f8fafc;">
            <div style="max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;">
                <h2 style="color: #6366f1;">Test Email Réussi !</h2>
                <p>Ceci est un email de test pour vérifier que le système d\'envoi fonctionne correctement.</p>
                <p><strong>Heure du test :</strong> ' . current_time('d/m/Y H:i:s') . '</p>
                <div style="background: #f0fdf4; padding: 15px; border-radius: 6px; border-left: 4px solid #10b981;">
                    <p style="margin: 0; color: #065f46;">✅ Configuration email opérationnelle</p>
                </div>
            </div>
        </div>';
        
        return $this->send_email($test_email, $subject, $message);
    }
}
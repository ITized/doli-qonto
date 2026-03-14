<?php
/* Copyright (C) 2025 Finta Ionut <ionut.finta@itized.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       class/qontoapi.class.php
 * \ingroup    doliqonto
 * \brief      File for Qonto API communication class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class QontoApi
 * Handles communication with Qonto API
 */
class QontoApi
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var array Error messages
	 */
	public $errors = array();

	/**
	 * @var string API URL
	 */
	private $apiUrl;

	/**
	 * @var string OAuth URL
	 */
	private $oauthUrl;

	/**
	 * @var string Authentication method (api_key or oauth2)
	 */
	private $authMethod;

	/**
	 * @var string API Key (for classic authentication)
	 */
	private $apiKey;

	/**
	 * @var string OAuth Client ID
	 */
	private $clientId;

	/**
	 * @var string OAuth Client Secret
	 */
	private $clientSecret;

	/**
	 * @var string Access Token
	 */
	private $accessToken;

	/**
	 * @var string Refresh Token
	 */
	private $refreshToken;

	/**
	 * @var int Token expiration timestamp
	 */
	private $tokenExpiresAt;

	/**
	 * @var string Organization slug
	 */
	private $organizationSlug;

	/**
	 * @var string Qonto Staging Token (for sandbox)
	 */
	private $stagingToken;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		// API base URL - should NOT include /v2 (will be added in endpoints)
		$apiUrlBase = getDolGlobalString('QONTO_API_URL', 'https://thirdparty.qonto.com');
		// Remove trailing /v2 if present to avoid duplication
		$this->apiUrl = rtrim($apiUrlBase, '/');
		$this->apiUrl = preg_replace('#/v2$#', '', $this->apiUrl);
		
		$this->oauthUrl = getDolGlobalString('QONTO_OAUTH_URL', 'https://oauth.qonto.com');
		
		// Get authentication method
		$this->authMethod = getDolGlobalString('QONTO_AUTH_METHOD', 'api_key');
		
		// Load credentials based on auth method
		if ($this->authMethod == 'oauth2') {
			$this->clientId = getDolGlobalString('QONTO_OAUTH_CLIENT_ID', '');
			$this->clientSecret = getDolGlobalString('QONTO_OAUTH_CLIENT_SECRET', '');
			$this->accessToken = getDolGlobalString('QONTO_ACCESS_TOKEN', '');
			$this->refreshToken = getDolGlobalString('QONTO_REFRESH_TOKEN', '');
			$this->tokenExpiresAt = (int)getDolGlobalString('QONTO_TOKEN_EXPIRES_AT', 0);
		} else {
			// API Key authentication
			$this->apiKey = getDolGlobalString('QONTO_API_KEY', '');
			$this->organizationSlug = getDolGlobalString('QONTO_ORGANIZATION_SLUG', '');
		}

		// Load staging token (for sandbox)
		$this->stagingToken = getDolGlobalString('QONTO_STAGING_TOKEN', '');
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method HTTP method (GET, POST, PUT, DELETE)
	 * @param array $data Request data
	 * @return array|false Response data or false on error
	 */
	private function makeRequest($endpoint, $method = 'GET', $data = array())
	{
		if ($this->authMethod == 'oauth2') {
			// OAuth2 authentication
			// Check if token needs refresh
			if ($this->tokenExpiresAt > 0 && $this->tokenExpiresAt < time() + 300) {
				// Token expires in less than 5 minutes, refresh it
				if (!empty($this->refreshToken)) {
					$this->refreshAccessToken();
				}
			}

			if (empty($this->accessToken)) {
				$this->error = 'Qonto access token is not configured. Please connect with Qonto.';
				return false;
			}

			$authHeader = 'Authorization: Bearer ' . $this->accessToken;
		} else {
			// API Key authentication
			if (empty($this->apiKey)) {
				$this->error = 'Qonto API key is not configured';
				return false;
			}

			if (empty($this->organizationSlug)) {
				$this->error = 'Qonto organization slug is not configured';
				return false;
			}

			// Classic API authentication format: organization_slug:api_key
			$authHeader = 'Authorization: ' . $this->organizationSlug . ':' . $this->apiKey;
		}

		$url = $this->apiUrl . $endpoint;

		$headers = array(
			$authHeader,
			'Content-Type: application/json',
			'Accept: application/json'
		);

		// Add X-Qonto-Staging-Token header if staging token is set
		if (!empty($this->stagingToken)) {
			$headers[] = 'X-Qonto-Staging-Token: ' . $this->stagingToken;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			if (!empty($data)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			}
		} elseif ($method === 'PUT') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			if (!empty($data)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			}
		} elseif ($method === 'DELETE') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);
		if ($curlError) {
			dol_syslog("QontoApi::makeRequest - cURL Error: " . $curlError, LOG_ERR);
		}
		if ($httpCode >= 400) {
			dol_syslog("QontoApi::makeRequest - Error Response: " . $response, LOG_ERR);
		}

		if ($curlError) {
			$this->error = 'cURL Error: ' . $curlError;
			return false;
		}

		if ($httpCode >= 400) {
			$this->error = 'HTTP Error ' . $httpCode . ': ' . $response;
			dol_syslog("QontoApi::makeRequest - URL: " . $url . " Auth: " . $this->authMethod, LOG_DEBUG);
			return false;
		}

		$decoded = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->error = 'JSON decode error: ' . json_last_error_msg();
			return false;
		}

		return $decoded;
	}

	/**
	 * Generate OAuth2 authorization URL
	 *
	 * @return string Authorization URL
	 */
	public function getAuthorizationUrl()
	{
		global $conf, $dolibarr_main_url_root;

		if (empty($this->clientId)) {
			return '#';
		}

		// Generate and store state for CSRF protection
		$state = bin2hex(random_bytes(16));
		$_SESSION['qonto_oauth_state'] = $state;

		// Build redirect URI
		$redirectUri = $dolibarr_main_url_root . '/custom/doliqonto/admin/oauth_callback.php';

		// Build authorization URL
		$params = array(
			'client_id' => $this->clientId,
			'redirect_uri' => $redirectUri,
			'response_type' => 'code',
			'state' => $state,
			'scope' => 'offline_access transactions.read attachments.read organization.read'
		);

		return $this->oauthUrl . '/oauth2/auth?' . http_build_query($params);
	}

	/**
	 * Exchange authorization code for access and refresh tokens
	 *
	 * @param string $code Authorization code
	 * @return int 1 if success, -1 if error
	 */
	public function exchangeCodeForTokens($code)
	{
		global $conf, $dolibarr_main_url_root;

		if (empty($this->clientId) || empty($this->clientSecret)) {
			$this->error = 'OAuth client credentials are not configured';
			return -1;
		}

		$redirectUri = $dolibarr_main_url_root . '/custom/doliqonto/admin/oauth_callback.php';

		$postData = array(
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'grant_type' => 'authorization_code',
			'code' => $code,
			'redirect_uri' => $redirectUri
		);

		$result = $this->makeOAuthRequest('/oauth2/token', $postData);

		if ($result === false) {
			return -1;
		}

		// Store tokens
		return $this->saveTokens($result);
	}

	/**
	 * Refresh access token using refresh token
	 *
	 * @return int 1 if success, -1 if error
	 */
	public function refreshAccessToken()
	{
		if (empty($this->clientId) || empty($this->clientSecret) || empty($this->refreshToken)) {
			$this->error = 'Cannot refresh token: missing credentials or refresh token';
			return -1;
		}

		$postData = array(
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $this->refreshToken
		);

		$result = $this->makeOAuthRequest('/oauth2/token', $postData);

		if ($result === false) {
			return -1;
		}

		// Store new tokens
		return $this->saveTokens($result);
	}

	/**
	 * Make OAuth request
	 *
	 * @param string $endpoint OAuth endpoint
	 * @param array $postData POST data
	 * @return array|false Response data or false on error
	 */
	private function makeOAuthRequest($endpoint, $postData)
	{
		$url = $this->oauthUrl . $endpoint;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded',
			'Accept: application/json'
		));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($curlError) {
			$this->error = 'cURL Error: ' . $curlError;
			return false;
		}

		if ($httpCode >= 400) {
			$this->error = 'HTTP Error ' . $httpCode . ': ' . $response;
			return false;
		}

		$decoded = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->error = 'JSON decode error: ' . json_last_error_msg();
			return false;
		}

		return $decoded;
	}

	/**
	 * Save OAuth tokens to database
	 *
	 * @param array $tokenData Token data from OAuth response
	 * @return int 1 if success, -1 if error
	 */
	private function saveTokens($tokenData)
	{
		global $conf;

		if (empty($tokenData['access_token'])) {
			$this->error = 'No access token in response';
			return -1;
		}

		$expiresAt = time() + (int)($tokenData['expires_in'] ?? 3600);

		// Save tokens to configuration
		dolibarr_set_const($this->db, 'QONTO_ACCESS_TOKEN', $tokenData['access_token'], 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($this->db, 'QONTO_TOKEN_EXPIRES_AT', $expiresAt, 'chaine', 0, '', $conf->entity);

		if (!empty($tokenData['refresh_token'])) {
			dolibarr_set_const($this->db, 'QONTO_REFRESH_TOKEN', $tokenData['refresh_token'], 'chaine', 0, '', $conf->entity);
		}

		// Update internal properties
		$this->accessToken = $tokenData['access_token'];
		$this->tokenExpiresAt = $expiresAt;
		if (!empty($tokenData['refresh_token'])) {
			$this->refreshToken = $tokenData['refresh_token'];
		}

		// Fetch and save organization slug if not set
		if (empty($this->organizationSlug)) {
			$orgData = $this->getOrganization();
			if ($orgData && !empty($orgData['organization']['slug'])) {
				dolibarr_set_const($this->db, 'QONTO_ORGANIZATION_SLUG', $orgData['organization']['slug'], 'chaine', 0, '', $conf->entity);
				$this->organizationSlug = $orgData['organization']['slug'];
			}
		}

		return 1;
	}

	/**
	 * Test API connection
	 *
	 * @return int 1 if success, -1 if error
	 */
	public function testConnection()
	{
		$result = $this->getOrganization();
		if ($result === false) {
			return -1;
		}
		return 1;
	}

	/**
	 * Get organization details
	 *
	 * @return array|false Organization data or false on error
	 */
	public function getOrganization()
	{
		// Both OAuth2 and API Key use the same endpoint: /v2/organization
		return $this->makeRequest('/v2/organization');
	}

	/**
	 * Get linked bank accounts from Dolibarr
	 *
	 * @return array Array of linked bank accounts with mapping info
	 */
	public function getLinkedBankAccounts()
	{
		$linkedAccounts = array();
		
		$sql = "SELECT ba.rowid, ba.ref, ba.label, ef.qonto_bank_id, ef.qonto_name";
		$sql .= " FROM ".MAIN_DB_PREFIX."bank_account as ba";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."bank_account_extrafields as ef ON ef.fk_object = ba.rowid";
		$sql .= " WHERE ef.qonto_bank_id IS NOT NULL AND ef.qonto_bank_id != ''";
		$sql .= " AND ba.entity IN (".getEntity('bank_account').")";
		
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$linkedAccounts[] = array(
					'dolibarr_id' => $obj->rowid,
					'dolibarr_ref' => $obj->ref,
					'dolibarr_label' => $obj->label,
					'qonto_bank_id' => $obj->qonto_bank_id,
					'qonto_name' => $obj->qonto_name
				);
			}
			$this->db->free($resql);
		}
		
		return $linkedAccounts;
	}

	/**
	 * List transactions
	 *
	 * @param string $bankAccountId Bank account ID
	 * @param array $filters Filters (settled_at_from, settled_at_to, status, etc.)
	 * @param int $perPage Results per page
	 * @param int $page Current page
	 * @return array|false Transactions data or false on error
	 */
	public function listTransactions($bankAccountId = null, $filters = array(), $perPage = 100, $page = 1)
	{
		if (empty($bankAccountId)) {
			$bankAccountId = getDolGlobalString('QONTO_BANK_ACCOUNT_ID');
		}

		// Bank account ID is required by Qonto API
		if (empty($bankAccountId)) {
			$this->error = 'Bank account ID is required. Please configure QONTO_BANK_ACCOUNT_ID in module settings.';
			return false;
		}

		$params = array(
			'bank_account_id' => $bankAccountId,
			'per_page' => $perPage,
			'page' => $page
		);

		// Add filters
		foreach ($filters as $key => $value) {
			if (!empty($value)) {
				$params[$key] = $value;
			}
		}

		$queryString = http_build_query($params);
		$endpoint = '/v2/transactions?' . $queryString;

		return $this->makeRequest($endpoint);
	}

	/**
	 * Get single transaction
	 *
	 * @param string $transactionId Transaction ID
	 * @return array|false Transaction data or false on error
	 */
	public function getTransaction($transactionId)
	{
		return $this->makeRequest('/v2/transactions/' . $transactionId);
	}

	/**
	 * List attachments for a transaction
	 *
	 * @param string $transactionId Transaction ID
	 * @return array|false Attachments data or false on error
	 */
	public function listTransactionAttachments($transactionId)
	{
		return $this->makeRequest('/v2/transactions/' . $transactionId . '/attachments');
	}

	/**
	 * Get attachment details
	 *
	 * @param string $attachmentId Attachment ID
	 * @return array|false Attachment data or false on error
	 */
	public function getAttachment($attachmentId)
	{
		return $this->makeRequest('/v2/attachments/' . $attachmentId);
	}

	/**
	 * Upload attachment to transaction
	 *
	 * @param string $transactionId Transaction ID
	 * @param string $filePath Local file path
	 * @param string $filename Filename
	 * @return array|false Response data or false on error
	 */
	public function uploadAttachment($transactionId, $filePath, $filename, $rawData = '')
	{
		if (!file_exists($filePath)) {
			$this->error = 'File not found: ' . $filePath;
			dol_syslog("QontoApi::uploadAttachment - " . $this->error, LOG_ERR);
			return false;
		}

		// Try to get the UUID 'id' from raw_data (Qonto v2 API may use 'id' instead of 'transaction_id')
		$apiId = $transactionId;
		if (!empty($rawData)) {
			$data = json_decode($rawData, true);
			if ($data && !empty($data['id'])) {
				$apiId = $data['id'];
				dol_syslog("QontoApi::uploadAttachment - Using 'id' from raw_data: " . $apiId . " (transaction_id was: " . $transactionId . ")", LOG_DEBUG);
			}
		}

		dol_syslog("QontoApi::uploadAttachment - Uploading " . $filename . " to transaction " . $apiId, LOG_DEBUG);

		// Qonto API requires multipart/form-data for file uploads
		return $this->makeFileUploadRequest('/v2/transactions/' . $apiId . '/attachments', $filePath, $filename);
	}

	/**
	 * Upload a file to Qonto API using multipart/form-data
	 *
	 * @param string $endpoint API endpoint
	 * @param string $filePath Full path to the file
	 * @param string $filename Filename for the upload
	 * @return array|false API response or false on error
	 */
	private function makeFileUploadRequest($endpoint, $filePath, $filename)
	{
		if ($this->authMethod == 'oauth2') {
			if ($this->tokenExpiresAt > 0 && $this->tokenExpiresAt < time() + 300) {
				if (!empty($this->refreshToken)) {
					$this->refreshAccessToken();
				}
			}
			if (empty($this->accessToken)) {
				$this->error = 'Qonto access token is not configured.';
				return false;
			}
			$authHeader = 'Authorization: Bearer ' . $this->accessToken;
		} else {
			if (empty($this->apiKey) || empty($this->organizationSlug)) {
				$this->error = 'Qonto API key or organization slug is not configured';
				return false;
			}
			$authHeader = 'Authorization: ' . $this->organizationSlug . ':' . $this->apiKey;
		}

		$url = $this->apiUrl . $endpoint;

		$headers = array(
			$authHeader,
			'Accept: application/json',
			'X-Qonto-Idempotency-Key: ' . $this->generateUUID()
		);

		if (!empty($this->stagingToken)) {
			$headers[] = 'X-Qonto-Staging-Token: ' . $this->stagingToken;
		}

		$cfile = new CURLFile($filePath, mime_content_type($filePath), $filename);
		$postData = array('file' => $cfile);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		dol_syslog("QontoApi::makeFileUploadRequest - URL: " . $url . " HTTP: " . $httpCode, LOG_DEBUG);

		if ($curlError) {
			$this->error = 'cURL Error: ' . $curlError;
			dol_syslog("QontoApi::makeFileUploadRequest - " . $this->error, LOG_ERR);
			return false;
		}

		if ($httpCode >= 400) {
			$this->error = 'HTTP Error ' . $httpCode . ': ' . $response;
			dol_syslog("QontoApi::makeFileUploadRequest - " . $this->error, LOG_ERR);
			return false;
		}

		// Some endpoints return empty body on success (e.g., attachment upload returns 200 with no body)
		if (empty($response)) {
			return array('success' => true);
		}

		$decoded = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->error = 'JSON decode error: ' . json_last_error_msg();
			return false;
		}

		return $decoded;
	}

	/**
	 * Generate a UUID v4 for idempotency keys
	 *
	 * @return string UUID v4 string
	 */
	private function generateUUID()
	{
		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/**
	 * Download attachment from Qonto
	 *
	 * @param string $attachmentId Attachment ID
	 * @param string $destinationPath Destination file path
	 * @return bool True on success, false on error
	 */
	public function downloadAttachment($attachmentId, $destinationPath)
	{
		$attachmentData = $this->getAttachment($attachmentId);
		if ($attachmentData === false) {
			return false;
		}

		if (empty($attachmentData['attachment']['url'])) {
			$this->error = 'Attachment URL not found';
			return false;
		}

		$url = $attachmentData['attachment']['url'];

		// Validate URL domain to prevent SSRF
		$parsedUrl = parse_url($url);
		if (empty($parsedUrl['host']) || !preg_match('/(\.)?qonto\.(com|co)$/i', $parsedUrl['host'])) {
			$this->error = 'Invalid attachment URL domain';
			return false;
		}

		$fileContent = file_get_contents($url);
		
		if ($fileContent === false) {
			$this->error = 'Failed to download attachment from URL';
			return false;
		}

		$result = file_put_contents($destinationPath, $fileContent);
		if ($result === false) {
			$this->error = 'Failed to save attachment to: ' . $destinationPath;
			return false;
		}

		return true;
	}

	/**
	 * Sync transactions from Qonto
	 *
	 * @param int $daysBack Number of days to fetch backwards
	 * @param int $dolibarrBankAccountId Specific Dolibarr bank account ID (optional, if not set will sync all linked accounts)
	 * @return int Number of transactions imported, -1 on error
	 */
	public function syncTransactions($daysBack = null, $dolibarrBankAccountId = null)
	{
		global $conf, $user;

		if (empty($daysBack)) {
			$daysBack = getDolGlobalInt('QONTO_SYNC_DAYS_BACK', 30);
		}

		$totalImported = 0;

		// If specific Dolibarr bank account is provided, sync only that one
		if ($dolibarrBankAccountId > 0) {
			$sql = "SELECT qonto_bank_id FROM ".MAIN_DB_PREFIX."bank_account_extrafields";
			$sql .= " WHERE fk_object = ".(int)$dolibarrBankAccountId;
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if ($obj && !empty($obj->qonto_bank_id)) {
					$imported = $this->syncTransactionsForAccount($obj->qonto_bank_id, $daysBack);
					if ($imported >= 0) {
						$totalImported += $imported;
					} else {
						return -1;
					}
				} else {
					$this->error = 'This Dolibarr bank account is not linked to a Qonto account';
					return -1;
				}
			}
		} else {
			// Sync all linked bank accounts
			$linkedAccounts = $this->getLinkedBankAccounts();
			
			if (empty($linkedAccounts)) {
				$this->error = 'No bank accounts are linked. Please link at least one Dolibarr bank account to a Qonto account.';
				return -1;
			}
			
			// Sync each linked account
			foreach ($linkedAccounts as $linkedAccount) {
				$imported = $this->syncTransactionsForAccount($linkedAccount['qonto_bank_id'], $daysBack);
				if ($imported >= 0) {
					$totalImported += $imported;
				}
				// Continue with other accounts even if one fails
			}
		}

		return $totalImported;
	}

	/**
	 * Sync transactions for a specific Qonto bank account
	 *
	 * @param string $bankAccountId Qonto bank account ID
	 * @param int $daysBack Number of days to fetch backwards
	 * @return int Number of transactions imported, -1 on error
	 */
	private function syncTransactionsForAccount($bankAccountId, $daysBack)
	{
		global $conf, $user;

		if (empty($bankAccountId)) {
			return 0;
		}

		dol_include_once('/doliqonto/class/qontotransaction.class.php');

		$dateFrom = date('Y-m-d', strtotime('-' . $daysBack . ' days'));
		
		$filters = array(
			'settled_at_from' => $dateFrom
		);

		$result = $this->listTransactions($bankAccountId, $filters, 100, 1);
		
		if ($result === false) {
			return -1;
		}

		$imported = 0;
		
		if (!empty($result['transactions'])) {
			foreach ($result['transactions'] as $transactionData) {
				$transaction = new QontoTransaction($this->db);
				
				// Check if transaction already exists
				$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "qonto_transactions";
				$sql .= " WHERE transaction_id = '" . $this->db->escape($transactionData['transaction_id']) . "'";
				$sql .= " AND entity = " . $conf->entity;
				
				$resql = $this->db->query($sql);
				if ($resql) {
					$num = $this->db->num_rows($resql);
					if ($num > 0) {
						// Transaction already exists, skip
						continue;
					}
				}
				
				// Import transaction
				$transaction->transaction_id = $transactionData['transaction_id'];
				$transaction->bank_account_id = $transactionData['bank_account_id'];
				$transaction->emitted_at = $this->db->idate($transactionData['emitted_at']);
				$transaction->settled_at = !empty($transactionData['settled_at']) ? $this->db->idate($transactionData['settled_at']) : null;
				$transaction->amount = $transactionData['amount'];
				$transaction->currency = $transactionData['currency'];
				$transaction->side = $transactionData['side'];
				$transaction->operation_type = $transactionData['operation_type'];
				$transaction->status = $transactionData['status'];
				$transaction->label = $transactionData['label'];
				$transaction->note = !empty($transactionData['note']) ? $transactionData['note'] : '';
				$transaction->reference = !empty($transactionData['reference']) ? $transactionData['reference'] : '';
				$transaction->vat_amount = !empty($transactionData['vat_amount']) ? $transactionData['vat_amount'] : 0;
				$transaction->vat_rate = !empty($transactionData['vat_rate']) ? $transactionData['vat_rate'] : 0;
				$transaction->counterparty_name = !empty($transactionData['counterparty']['name']) ? $transactionData['counterparty']['name'] : '';
				$transaction->counterparty_iban = !empty($transactionData['counterparty']['iban']) ? $transactionData['counterparty']['iban'] : '';
				$transaction->attachment_ids = !empty($transactionData['attachment_ids']) ? json_encode($transactionData['attachment_ids']) : '';
				$transaction->raw_data = json_encode($transactionData);
				$transaction->entity = $conf->entity;
				
				$result = $transaction->create($user);
				if ($result > 0) {
					$imported++;
					
					// Try to auto-match with existing Dolibarr bank line
					$transaction->autoMatch($user);
				}
			}
		}

		return $imported;
	}

	/**
	 * Refresh attachment IDs for existing transactions
	 * Useful when attachments were uploaded to Qonto after initial sync
	 *
	 * @param int $daysBack Number of days to look back (default 30)
	 * @return int Number of transactions updated
	 */
	public function refreshAttachmentIds($daysBack = 30)
	{
		global $conf, $user, $db;
		
		$updated = 0;
		$startDate = date('Y-m-d', strtotime("-$daysBack days"));
		
		// Get all linked Qonto accounts
		$sql = "SELECT qonto_bank_id FROM ".MAIN_DB_PREFIX."bank_account_extrafields";
		$sql .= " WHERE qonto_bank_id != ''";
		$sql .= " AND qonto_bank_id IS NOT NULL";
		
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog("QontoApi::refreshAttachmentIds - Failed to query bank accounts", LOG_ERR);
			return -1;
		}
		
		$accountCount = 0;
		while ($obj = $db->fetch_object($resql)) {
			$qontoBankId = $obj->qonto_bank_id;
			$accountCount++;
			
			dol_syslog("QontoApi::refreshAttachmentIds - Fetching transactions for account ".$qontoBankId." from ".$startDate, LOG_INFO);
			
			// Fetch recent transactions from Qonto
			// Build query string for GET request
			$queryParams = http_build_query(array(
				'bank_account_id' => $qontoBankId,
				'settled_at_from' => $startDate,
				'per_page' => 100
			));
			$response = $this->makeRequest('/v2/transactions?'.$queryParams, 'GET', array());
			
			if ($response === false) {
				dol_syslog("QontoApi::refreshAttachmentIds - API request failed for account ".$qontoBankId, LOG_WARNING);
				continue;
			}
			
			if (!isset($response['transactions'])) {
				dol_syslog("QontoApi::refreshAttachmentIds - No transactions in response for account ".$qontoBankId, LOG_WARNING);
				continue;
			}
			
			$txCount = count($response['transactions']);
			dol_syslog("QontoApi::refreshAttachmentIds - Received ".$txCount." transactions from Qonto", LOG_INFO);
			
			foreach ($response['transactions'] as $qontoTx) {
				// Find matching transaction in database
				$sqlFind = "SELECT rowid, attachment_ids FROM ".MAIN_DB_PREFIX."qonto_transactions";
				$sqlFind .= " WHERE transaction_id = '".$db->escape($qontoTx['transaction_id'])."'";
				$sqlFind .= " AND entity = ".$conf->entity;
				
				$resqlFind = $db->query($sqlFind);
				if ($resqlFind && $db->num_rows($resqlFind) > 0) {
					$objTx = $db->fetch_object($resqlFind);
					$oldAttachments = $objTx->attachment_ids;
					$newAttachments = !empty($qontoTx['attachment_ids']) ? json_encode($qontoTx['attachment_ids']) : '';
					
					// Only update if attachments changed
					if ($oldAttachments != $newAttachments) {
						$sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."qonto_transactions";
						$sqlUpdate .= " SET attachment_ids = '".$db->escape($newAttachments)."'";
						$sqlUpdate .= " WHERE rowid = ".(int)$objTx->rowid;
						
						if ($db->query($sqlUpdate)) {
							$updated++;
							dol_syslog("QontoApi::refreshAttachmentIds - Updated transaction ".$qontoTx['transaction_id']." - Old: ".$oldAttachments." -> New: ".$newAttachments, LOG_INFO);
						}
					}
				}
			}
		}
		
		dol_syslog("QontoApi::refreshAttachmentIds - Processed ".$accountCount." accounts, updated ".$updated." transactions", LOG_INFO);
		return $updated;
	}
}

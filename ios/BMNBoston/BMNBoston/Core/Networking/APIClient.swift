import Foundation

/// Actor-based API client for thread-safe network requests
actor APIClient {
    static let shared = APIClient()

    private let session: URLSession
    private let baseURL: URL
    private var accessToken: String?
    private var refreshToken: String?

    private init(
        baseURL: URL = URL(string: "http://localhost:8080/wp-json/bmn/v1")!,
        session: URLSession = .shared
    ) {
        self.baseURL = baseURL
        self.session = session
    }

    /// Perform an API request with automatic token refresh
    func request<T: Decodable>(_ endpoint: Endpoint) async throws -> T {
        var request = try endpoint.urlRequest(baseURL: baseURL)

        if let token = accessToken {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        let (data, response) = try await session.data(for: request)

        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }

        // Handle 401 - attempt token refresh
        if httpResponse.statusCode == 401, refreshToken != nil {
            try await refreshAccessToken()
            return try await self.request(endpoint) // Retry once
        }

        guard 200..<300 ~= httpResponse.statusCode else {
            throw APIError.httpError(statusCode: httpResponse.statusCode, data: data)
        }

        let apiResponse = try JSONDecoder.bmn.decode(APIResponse<T>.self, from: data)

        guard apiResponse.success else {
            throw APIError.apiError(message: apiResponse.error?.message ?? "Unknown error")
        }

        guard let responseData = apiResponse.data else {
            throw APIError.noData
        }

        return responseData
    }

    /// Set authentication tokens
    func setTokens(access: String, refresh: String) {
        self.accessToken = access
        self.refreshToken = refresh
    }

    /// Clear authentication tokens
    func clearTokens() {
        self.accessToken = nil
        self.refreshToken = nil
    }

    private func refreshAccessToken() async throws {
        guard let refreshToken else {
            throw APIError.notAuthenticated
        }

        // TODO: Implement token refresh endpoint call
        _ = refreshToken
    }
}

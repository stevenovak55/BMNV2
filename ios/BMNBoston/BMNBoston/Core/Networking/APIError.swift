import Foundation

enum APIError: LocalizedError {
    case invalidURL
    case invalidResponse
    case httpError(statusCode: Int, data: Data)
    case apiError(message: String)
    case noData
    case notAuthenticated
    case decodingError(Error)

    var errorDescription: String? {
        switch self {
        case .invalidURL:
            return "Invalid URL"
        case .invalidResponse:
            return "Invalid response from server"
        case .httpError(let statusCode, _):
            return "HTTP error: \(statusCode)"
        case .apiError(let message):
            return message
        case .noData:
            return "No data in response"
        case .notAuthenticated:
            return "Not authenticated"
        case .decodingError(let error):
            return "Decoding error: \(error.localizedDescription)"
        }
    }
}

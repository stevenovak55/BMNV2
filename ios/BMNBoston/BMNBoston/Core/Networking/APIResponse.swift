import Foundation

/// Standard API response wrapper matching the server format
struct APIResponse<T: Decodable>: Decodable {
    let success: Bool
    let data: T?
    let meta: ResponseMeta?
    let error: ResponseError?
}

struct ResponseMeta: Decodable {
    let total: Int?
    let page: Int?
    let perPage: Int?
    let pages: Int?

    enum CodingKeys: String, CodingKey {
        case total, page, pages
        case perPage = "per_page"
    }
}

struct ResponseError: Decodable {
    let message: String
    let code: Int?
    let details: [String: String]?
}

extension JSONDecoder {
    /// Configured decoder for BMN API responses (snake_case)
    static let bmn: JSONDecoder = {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        decoder.dateDecodingStrategy = .custom { decoder in
            let container = try decoder.singleValueContainer()
            let dateString = try container.decode(String.self)

            // Try ISO 8601 first
            if let date = ISO8601DateFormatter().date(from: dateString) {
                return date
            }

            // Try MySQL datetime format
            let formatter = DateFormatter()
            formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
            formatter.timeZone = TimeZone(identifier: "America/New_York")
            if let date = formatter.date(from: dateString) {
                return date
            }

            throw DecodingError.dataCorruptedError(
                in: container,
                debugDescription: "Cannot decode date: \(dateString)"
            )
        }
        return decoder
    }()
}

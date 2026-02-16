import XCTest
@testable import BMNBoston

final class BMNBostonTests: XCTestCase {
    func testPropertyDecoding() throws {
        let json = """
        {
            "id": 1,
            "listing_id": "73123456",
            "address": "123 Main St",
            "city": "Boston",
            "state": "MA",
            "zip": "02101",
            "price": 750000,
            "beds": 3.0,
            "baths": 2.0,
            "sqft": 1500,
            "status": "Active"
        }
        """.data(using: .utf8)!

        let property = try JSONDecoder.bmn.decode(Property.self, from: json)

        XCTAssertEqual(property.id, 1)
        XCTAssertEqual(property.listingId, "73123456")
        XCTAssertEqual(property.address, "123 Main St")
        XCTAssertEqual(property.city, "Boston")
        XCTAssertEqual(property.price, 750000)
        XCTAssertEqual(property.beds, 3.0)
    }

    func testUserDecoding() throws {
        let json = """
        {
            "id": 42,
            "email": "test@example.com",
            "display_name": "Test User",
            "role": "subscriber",
            "agent_id": null
        }
        """.data(using: .utf8)!

        let user = try JSONDecoder.bmn.decode(User.self, from: json)

        XCTAssertEqual(user.id, 42)
        XCTAssertEqual(user.email, "test@example.com")
        XCTAssertEqual(user.displayName, "Test User")
        XCTAssertNil(user.agentId)
    }

    func testAPIResponseDecoding() throws {
        let json = """
        {
            "success": true,
            "data": {
                "id": 1,
                "listing_id": "73100001",
                "address": "456 Beacon St",
                "city": "Boston",
                "state": "MA",
                "zip": "02115",
                "price": 1200000,
                "status": "Active"
            },
            "meta": {
                "total": 1,
                "page": 1,
                "per_page": 50,
                "pages": 1
            }
        }
        """.data(using: .utf8)!

        let response = try JSONDecoder.bmn.decode(APIResponse<Property>.self, from: json)

        XCTAssertTrue(response.success)
        XCTAssertNotNil(response.data)
        XCTAssertEqual(response.data?.listingId, "73100001")
        XCTAssertEqual(response.meta?.total, 1)
    }

    func testSchoolDecoding() throws {
        let json = """
        {
            "id": 10,
            "name": "Boston Latin School",
            "level": "High",
            "grade": "A+",
            "city": "Boston",
            "district": "Boston Public Schools",
            "composite_score": 95.5
        }
        """.data(using: .utf8)!

        let school = try JSONDecoder.bmn.decode(School.self, from: json)

        XCTAssertEqual(school.name, "Boston Latin School")
        XCTAssertEqual(school.grade, "A+")
        XCTAssertEqual(school.compositeScore, 95.5)
    }
}

import SwiftUI

struct ContentView: View {
    @Environment(AppState.self) private var appState

    var body: some View {
        TabView(selection: Bindable(appState).selectedTab) {
            Tab("Search", systemImage: "magnifyingglass", value: .search) {
                Text("Property Search")
            }
            Tab("Favorites", systemImage: "heart", value: .favorites) {
                Text("Favorites")
            }
            Tab("Saved", systemImage: "bookmark", value: .savedSearches) {
                Text("Saved Searches")
            }
            Tab("Alerts", systemImage: "bell", value: .notifications) {
                Text("Notifications")
            }
            Tab("Profile", systemImage: "person", value: .profile) {
                Text("Profile")
            }
        }
    }
}

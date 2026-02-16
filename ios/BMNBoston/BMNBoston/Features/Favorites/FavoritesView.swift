import SwiftUI

struct FavoritesView: View {
    var body: some View {
        NavigationStack {
            Text("Favorites")
                .navigationTitle("Favorites")
        }
    }
}

#Preview {
    FavoritesView()
}

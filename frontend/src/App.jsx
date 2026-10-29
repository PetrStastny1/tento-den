import { Navigate, Route, Routes } from 'react-router'
import { Backdrop } from './components/Backdrop.jsx'
import { TabBar } from './components/TabBar.jsx'
import { useFavorites } from './lib/favorites.js'
import { DayScreen } from './screens/DayScreen.jsx'
import { FavoritesScreen } from './screens/FavoritesScreen.jsx'

function App() {
  const favorites = useFavorites()
  return (
    <>
      <Backdrop />
      <Routes>
        <Route path="/" element={<DayScreen favorites={favorites} />} />
        <Route path="/den/:date" element={<DayScreen favorites={favorites} />} />
        <Route path="/oblibene" element={<FavoritesScreen favorites={favorites} />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
      <TabBar />
    </>
  )
}

export default App

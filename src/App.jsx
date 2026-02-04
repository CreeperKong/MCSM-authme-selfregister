import { BrowserRouter, Route, Routes } from 'react-router-dom'
import './App.css'
import RegisterPage from './pages/RegisterPage.jsx'
import AdminPage from './pages/AdminPage.jsx'

function NotFound() {
  return (
    <div className="page-shell">
      <div className="panel">
        <h1>页面未找到</h1>
        <p>找不到你请求的页面，请检查链接。</p>
        <a className="btn" href="/">返回注册入口</a>
      </div>
    </div>
  )
}

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<RegisterPage />} />
        <Route path="/admin" element={<AdminPage />} />
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App

// import { useState } from 'react'
import { Toolbar } from './components/Toolbar';
import { Grid } from './components/Grid';
import { Controls } from './components/Controls';
import '../public/css/app.css'

function App() {
  // const [count, setCount] = useState(0)

  return (
    <>
      <Toolbar />
      <Grid />
      <Controls />
    </>
  )
}

export default App

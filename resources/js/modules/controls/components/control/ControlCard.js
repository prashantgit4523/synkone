
import { useState, useEffect, forwardRef, Fragment } from 'react';
import { Col, Row, Card, Dropdown, InputGroup, FormControl, Form } from "react-bootstrap";
import ReactApexChart from "react-apexcharts";
import { useDispatch, useSelector } from "react-redux";
import { fetchControlList } from "../../../../store/actions/controls/control";
import './control-card.scss';

function ControlCard(props) {

  const [status, setStatus] = useState('N/A');
  const [statusColor, setStatusColor] = useState('bg-black');
  const [target, setTarget] = useState(JSON.parse(props.control.targets));

  const dispatch = useDispatch();

  const [isSamaControl, setIsSamaControl] = useState(props.isSamaStandard);

  var currentYear = new Date().getFullYear();

  const chartOption = {
    colors: ["359f1d"],
    chart: {
      height: 350,
      type: 'radialBar',
      toolbar: {
        show: false
      }
    },
    plotOptions: {
      radialBar: {
        startAngle: -135,
        endAngle: 135,
        hollow: {
          margin: 0,
          size: '80%',
          background: '#fff',
          image: undefined,
          imageOffsetX: 0,
          imageOffsetY: 0,
          position: 'front',
          // dropShadow: {
          //     enabled: true,
          //     top: 3,
          //     left: 0,
          //     blur: 4,
          //     opacity: 0.01
          // }
        },
        track: {
          dropShadow: {
            enabled: false,
          }
        },
        dataLabels: {
          show: true,
          name: {
            offsetY: -10,
            show: false,
            color: '#343a40',
            fontSize: '17px'
          },
          value: {
            formatter: function (val) {
              return renderChartLabel(val);
            },
            color: '#6e6b7b',
            fontSize: '2.86rem',
            fontWeight: 400,
            show: true,
          }
        }
      }
    },
    fill: {
      type: 'gradient',
      colors: ['#359f1d'],
      gradient: {
        shade: 'light',
        type: 'horizontal',
        shadeIntensity: 1,
        gradientToColors: ['#359f1d'],
        inverseColors: true,
        opacityFrom: 1,
        opacityTo: 0.7,
        stops: [0, 100]
      }
    },
    stroke: {
      lineCap: 'round',
      width: 1
    },
    responsive: [{
      breakpoint: 768,
      options: {
        chart: {
          height: 250
        },
      }
    }],
    labels: ['Control Maturity'],
  }
  const chartSeries = [props.control.per]


  const renderChartLabel = (val) => {
    if (isSamaControl) {
      if (val == 0) {
        return 'Level 0';
      } else if (val > 0 && val <= 20) {
        return 'Level 1';
      } else if (val > 20 && val <= 40) {
        return 'Level 2';
      } else if (val > 40 && val <= 60) {
        return 'Level 3';
      } else if (val > 60 && val <= 80) {
        return 'Level 4';
      } else if (val > 80 && val <= 100) {
        return 'Level 5';
      }
    } else {
      return val + '%';
    }
  }

  useEffect(() => {
    computePassStatus();
  }, []);

  const computePassStatus = () => {
    let targets = JSON.parse(props.control.targets);
    if (targets && targets[currentYear] <= props.control.per) {
      setStatus('Passed');
      setStatusColor('bg-success');
    } else if (targets && targets[currentYear] > props.control.per) {
      setStatus('Failed');
      setStatusColor('bg-danger');
    } else {
      setStatus('N/A');
      setStatusColor('bg-black');
    }
  }

  const CustomMenu = forwardRef(
    ({ children, style, className, 'aria-labelledby': labeledBy }, ref) => {

      const selectedDepartments = useSelector(
        (store) =>
          store.commonReducer.departmentFilterReducer.selectedDepartment
      );
      const { selectedStandards } = useSelector(
        (store) => store.controlReducer.standardFilterReducer
      );

      const [isFormSubmitting, setIsFormSubmitting] = useState(false);

      const [values, setValues] = useState([]);
      var current_year = new Date().getFullYear();
      var three_years_array = [current_year, current_year + 1, current_year + 2];

      useEffect(() => {
        if (props.control.targets) {
          setValues(JSON.parse(props.control.targets));
        }
      }, [props]);

      const handleChange = (eValue, year, values) => {
        let previous_values = values;
        if (!previous_values) {
          previous_values = [];
        }
        previous_values[year] = eValue;
        setValues(previous_values);
      }

      const onTargetSave = () => {
        setIsFormSubmitting(true);
        axiosFetch.post(route('kpi.target.submit'), { id: props.control.control_id, values: values }).then(res => {
          if (res.data.success) {
            AlertBox({
              text: 'Target set successfully.',
              confirmButtonColor: "#b2dd4c",
              icon: 'success',
            });
          } else {
            AlertBox({
              text: res.data.message,
              confirmButtonColor: "#b2dd4c",
              icon: 'error',
            });
          }
          dispatch(
            fetchControlList({
              departments: selectedDepartments,
              standards: selectedStandards
            })
          );
          setIsFormSubmitting(false);
        });
      }

      const getDefaultTargetSelectValue = (year) => {
        let newTargetValues = JSON.parse(props.control.targets);
        if (newTargetValues) {
          let value = newTargetValues[year];
          if (value) {
            return value;
          }
        }
      }

      return (
        <div
          ref={ref}
          style={{ minWidth: '13rem' }}
          className={className}
          aria-labelledby={labeledBy}
        >
          {three_years_array.map(function (year, index) {
            return (
              <Row key={index}>
                <Col>
                  <span className='float-start col-md-6'>
                    <Form.Group className="year-form-label-kpi">
                      <Form.Label><i className="mdi mdi-table-edit font-14 me-1" />{year}</Form.Label>
                    </Form.Group>
                  </span>
                  <span className='float-end col-md-6'>
                    {
                      isSamaControl ?
                        <select
                          name="custom-datatable_length"
                          onChange={(e) => handleChange(e.target.value, year, values)}
                          aria-controls="custom-datatable"
                          className="form-select form-select-sm cursor-pointer form-control form-control-sm"
                          defaultValue={getDefaultTargetSelectValue(year)}
                        >
                          <option value="0">Level 0</option>
                          <option value="1">Level 1</option>
                          <option value="21">Level 2</option>
                          <option value="41">Level 3</option>
                          <option value="61">Level 4</option>
                          <option value="81">Level 5</option>
                        </select>
                        :
                        <InputGroup className="mb-3">
                          <FormControl
                            key={index}
                            type="number"
                            placeholder="%"
                            aria-label="%"
                            aria-describedby="basic-addon2"
                            defaultValue={values && values[year] ? values[year] : null}
                            onChange={(e) => handleChange(e.target.value, year, values)}
                          />
                          <InputGroup.Text id="basic-addon2">%</InputGroup.Text>
                        </InputGroup>
                    }
                  </span>
                </Col>
              </Row>
            );
          })
          }
          <Row className="text-center save-button-div-target" >
            <button onClick={() => onTargetSave()} type="button" disabled={isFormSubmitting} className="btn btn-xs btn-primary save me-5 custom-save-button">
              Save <i className="fas fa-check-circle text-medium"></i>
            </button>
          </Row>
        </div>
      );
    },
  );


  const renderTargetValue = () => {
    let value;
    if (isSamaControl && target && target[currentYear]) {
      if (target[currentYear] == '0') {
        value = 'Target: ' + 'Level 0'
      }
      if (target[currentYear] == 1) {
        value = 'Target: ' + 'Level 1'
      }
      if (target[currentYear] == 21) {
        value = 'Target: ' + 'Level 2'
      }
      if (target[currentYear] == 41) {
        value = 'Target: ' + 'Level 3'
      }
      if (target[currentYear] == 61) {
        value = 'Target: ' + 'Level 4'
      }
      if (target[currentYear] == 81) {
        value = 'Target: ' + 'Level 5'
      }
    } else if (target && target[currentYear] > 0) {
      value = 'Target: ' + target[currentYear] + '%'
    } else {
      value = 'Set Target'
    }
    return value;
  }

  const capitalizeFirstLetter = (str) => {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  return (
    <Fragment>
      <Col xl="4" sm="6" id="control-card-kpi" className="mb-3">
        <Card className='h-100'>
          <Card.Header>
            <Row style={{
              height: '8vh',
            }}>
              <Col>
                <Card.Title tag="h5" className="float-start text-wrap" dangerouslySetInnerHTML={{ __html: props.control.name }}></Card.Title>
              </Col>
            </Row>
            {/* <Row className='align-items-center justify-content-between'>
              <Col lg="10" className='pe-0'>
                <Card.Title tag="h5" className="title float-start">{props.control.name}</Card.Title>
              </Col>
              <Col lg="2" className='text-end ps-0'>
                <Dropdown className="cursor-pointer">
                  <Dropdown.Toggle as="span" className='set-target-dropdown-menu badge pill primary-bg-color'>
                    {target && target[currentYear] > 0 ?
                      'Target: ' + target[currentYear] + '%' : 'Set Target'
                    }
                  </Dropdown.Toggle>

                  <Dropdown.Menu className="dropdown-menu-end" as={CustomMenu} >
                    <Dropdown.Item eventKey="1" className="d-flex align-items-center">
                      <i className="mdi mdi-table-edit font-14 me-1" />2022
                      <input type='text'></input>
                    </Dropdown.Item>
                  </Dropdown.Menu>
                </Dropdown>
              </Col>
            </Row> */}
            <Row style={{ height: '1vh' }}>
              <Col>
                <span className="card-category float-start">{props.control.controlId}</span>
                <span className="card-category float-end">
                  <span className='float-end'>
                    <Dropdown className="cursor-pointer">
                      <Dropdown.Toggle as="span" className='set-target-dropdown-menu badge pill primary-bg-color'>
                        {renderTargetValue()}
                      </Dropdown.Toggle>

                      <Dropdown.Menu className="dropdown-menu-end" as={CustomMenu} >
                        <Dropdown.Item eventKey="1" className="d-flex align-items-center">
                          <i className="mdi mdi-table-edit font-14 me-1" />2022
                          <input type='text'></input>
                        </Dropdown.Item>
                      </Dropdown.Menu>
                    </Dropdown>
                  </span> <br />
                  <span>
                    Status: <span className={"badge " + statusColor + " rounded-pill"}>{status}</span>
                  </span>
                </span>
              </Col>
            </Row>
          </Card.Header>
          <Card.Body className='pb-0'>
            <Row>
              <ReactApexChart options={chartOption} series={chartSeries} type="radialBar" height={310} />
              <h4 className="text-center">Control Maturity</h4>
            </Row>
          </Card.Body>
          <Card.Footer className='pt-0'>
            <hr />
            {props.control.description &&
              <>
                {/* <Row>
                  <Col>
                    <div className="stats d-flex">
                      <div className="stats float-start me-2">
                        <i className="fa fa-info-circle" />
                      </div>
                      <div className="stats float-end flex-grow-1">{props.control.description}</div>
                    </div>
                  </Col>
                </Row> */}
                <Row>
                  <Col md="1" xs="2">
                    <i className="fa fa-info-circle" />
                  </Col>
                  <Col md="11" xs="10" className="ps-0">
                    {capitalizeFirstLetter(props.control.description)}
                  </Col>
                </Row>
                <br />
              </>
            }
            {/* <Row>
              <Col className="d-flex">
                <div className="stats float-start flex-grow-1">
                  <div className="stats float-start me-2">
                    <i className="fa fa-laptop" />
                  </div>
                  <div className="stats">
                    Total number of devices
                  </div>
                </div>
                <p className="card-category float-end">{props.control.total}</p>
              </Col>
            </Row> */}
            <Row>
              <Col md="1" xs="2">
                <i className="fa fa-laptop" />
              </Col>
              <Col md="10" xs="8" className="ps-0">
                {props.control.type_of_total}
              </Col>
              <Col md="1" xs="2" className="ps-0">
                {props.control.total}
              </Col>
            </Row>
          </Card.Footer>
        </Card>
      </Col>
      {/* <Col md="8">
            <Card className="card-chart">
            <Card.Header>
                <Card.Title tag="h5">Use of secret authentication information</Card.Title>
                <p className="card-category">A.9.3.1</p>
            </Card.Header>
            <Card.Body>
                <ReactApexChart options={chartOption2} series={chartSeries2} type="area" height={310} />
            </Card.Body>
            <Card.Footer>
                <div className="chart-legend">
                <i className="fa fa-circle text-success" /> 2021{" "}
                <i className="fa fa-circle text-primary" /> 2022
                </div>
                <hr />
                <div className="card-stats">
                <i className="fa fa-check" /> Updated just now
                </div>
            </Card.Footer>
            </Card>
        </Col> */}
    </Fragment >
  );
}

export default ControlCard;